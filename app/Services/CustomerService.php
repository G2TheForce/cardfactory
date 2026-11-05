<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerFields;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Customer records: the people whose details go on cards and digital profiles.
 * Handles normalising the flexible JSON groups, company linking, photo/logo
 * uploads and optional login accounts for customers.
 */
class CustomerService
{
    private const SIMPLE = [
        'full_name', 'professional_title', 'department', 'designation', 'mobile', 'whatsapp', 'office_phone', 'email',
        'website', 'country', 'province', 'city', 'office_address', 'map_url', 'latitude', 'longitude',
        'introduction', 'portfolio_url', 'booking_url',
    ];

    public function __construct(
        private readonly AssetService $assets,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Create or update a customer from validated input.
     *
     * @param  array<string, mixed>  $data  validated CustomerRequest data
     * @param  array{photo?: ?UploadedFile, company_logo?: ?UploadedFile}  $files
     *
     * @throws ValidationException
     */
    public function save(?Customer $customer, array $data, User $actor, array $files = [], ?User $designer = null): Customer
    {
        $isNew = $customer === null;
        $staff = $actor->can('customers.manage');

        if ($designer && ! $designer->can('designs.assigned') && ! $designer->can('designs.manage')) {
            throw ValidationException::withMessages(['assigned_designer' => 'This user is not a designer.']);
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($customer, $data, $actor, $files, $designer, $isNew, $staff, &$stored) {
                $customer ??= new Customer(['created_by' => $actor->id, 'status' => 'active']);

                $attributes = Arr::only($data, self::SIMPLE);
                foreach ($attributes as $key => $value) {
                    $attributes[$key] = is_string($value) ? trim($value) : $value;
                    if ($attributes[$key] === '') {
                        $attributes[$key] = null;
                    }
                }
                $customer->fill($attributes);

                $customer->social_links = $this->socialLinks($data['social_links'] ?? $customer->social_links ?? []);
                $customer->business = $this->business($data['business'] ?? $customer->business ?? [], $customer->business ?? []);
                // Groups missing from the input are left as they are (partial API updates).
                if ($isNew || array_key_exists('custom_fields', $data)) {
                    $customer->custom_fields = array_values(array_map(fn ($r) => ['label' => trim($r['label']), 'value' => trim((string) ($r['value'] ?? ''))], $data['custom_fields'] ?? []));
                }
                if ($isNew || array_key_exists('custom_links', $data)) {
                    $customer->custom_links = array_values(array_map(fn ($r) => ['label' => trim((string) ($r['label'] ?? '')) ?: parse_url($r['url'], PHP_URL_HOST), 'url' => $r['url']], $data['custom_links'] ?? []));
                }
                if ($isNew || array_key_exists('visibility', $data)) {
                    $customer->visibility = $this->visibility($data['visibility'] ?? []);
                }

                if ($staff) {
                    if (isset($data['status'])) {
                        $customer->status = $data['status'];
                    }
                    if (array_key_exists('assigned_designer', $data)) {
                        $customer->assigned_designer_id = $designer?->id;
                    }
                }

                $this->linkCompany($customer, $data, $actor, $files['company_logo'] ?? null, $stored);

                if (! empty($data['remove_photo']) && $customer->profile_photo_path) {
                    $old = $customer->profile_photo_path;
                    $customer->profile_photo_path = null;
                    DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                }
                if (($photo = $files['photo'] ?? null) instanceof UploadedFile) {
                    $customer->ulid ??= (string) Str::ulid();
                    $old = $customer->profile_photo_path;
                    $customer->profile_photo_path = $stored[] = $this->assets->storeImageFile($photo, 'customers/'.$customer->ulid, 1200, 'photo')['path'];
                    if ($old) {
                        DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                    }
                }

                $customer->save();

                if ($staff && ! empty($data['create_login'])) {
                    $this->createLogin($customer);
                }

                $this->logger->log($isNew ? 'customer.created' : 'customer.updated', $customer, ($isNew ? 'Created' : 'Updated').' customer "'.$customer->full_name.'"');

                return $customer;
            });
        } catch (\Throwable $e) {
            // Don't leave orphaned uploads behind when saving fails.
            Storage::disk('public')->delete($stored);
            throw $e;
        }
    }

    public function delete(Customer $customer): void
    {
        $this->logger->log('customer.deleted', $customer, 'Deleted customer "'.$customer->full_name.'"');
        $customer->delete();
    }

    public function restore(Customer $customer): void
    {
        $customer->restore();
        $this->logger->log('customer.restored', $customer, 'Restored customer "'.$customer->full_name.'"');
    }

    /**
     * Give a customer a login (role "customer") and email them a link to set a password.
     *
     * @throws ValidationException
     */
    public function createLogin(Customer $customer): User
    {
        if ($customer->user_id) {
            return $customer->user;
        }
        if (! $customer->email) {
            throw ValidationException::withMessages(['create_login' => 'Add an email address before creating a login.']);
        }
        if (User::withTrashed()->where('email', $customer->email)->exists()) {
            throw ValidationException::withMessages(['create_login' => 'An account with this email already exists.']);
        }

        $user = User::create([
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->mobile,
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->assignRole(config('smartcard.default_role', User::ROLE_CUSTOMER));
        $customer->user()->associate($user)->save();

        DB::afterCommit(fn () => Password::sendResetLink(['email' => $user->email]));
        $this->logger->log('customer.login_created', $customer, 'Created a login for "'.$customer->full_name.'"');

        return $user;
    }

    /** Visibility map with every known field, defaulting to visible. */
    public function visibility(array $input): array
    {
        $out = [];
        foreach (['card', 'profile'] as $channel) {
            foreach (array_keys(CustomerFields::VISIBLE_FIELDS) as $field) {
                $out[$channel][$field] = filter_var($input[$channel][$field] ?? true, FILTER_VALIDATE_BOOL);
            }
        }

        return $out;
    }

    /** Percentage of the useful card details that are filled in. */
    public function completeness(Customer $customer): int
    {
        $checks = [
            $customer->full_name, $customer->professional_title, $customer->mobile, $customer->email,
            $customer->website, $customer->office_address ?: $customer->city, $customer->profile_photo_path,
            $customer->company_id, ! empty($customer->social_links), $customer->introduction,
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    private function socialLinks(array $links): array
    {
        return collect(Arr::only($links, array_keys(CustomerFields::SOCIAL)))
            ->map(fn ($v) => is_string($v) ? trim($v) : null)->filter()->all();
    }

    private function business(array $input, array $current): array
    {
        $hours = collect(Arr::only($input['hours'] ?? [], CustomerFields::DAYS))->map(fn ($v) => trim((string) $v))->filter()->all();

        return array_filter([
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'services' => array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), $input['services'] ?? [])))),
            'hours' => $hours,
            // Kept for compatibility with data imported before the company form existed.
            'company_name' => $current['company_name'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Staff link customers to shared companies by name. Customers editing
     * themselves only ever change a company they own, never someone else's.
     */
    private function linkCompany(Customer $customer, array $data, User $actor, ?UploadedFile $logo, array &$stored): void
    {
        if (! array_key_exists('company_name', $data) && ! $logo && empty($data['remove_company_logo'])) {
            return;
        }

        $name = trim((string) ($data['company_name'] ?? ''));
        if ($name === '') {
            $customer->company_id = null;

            return;
        }

        $staff = $actor->can('customers.manage');
        $company = $customer->company;

        if ($staff) {
            $company = Company::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? Company::create(['name' => $name, 'slug' => $this->companySlug($name)]);
        } elseif (! $company || $company->owner_id !== $actor->id) {
            $company = Company::create(['name' => $name, 'slug' => $this->companySlug($name), 'owner_id' => $actor->id]);
        } elseif ($company->name !== $name) {
            $company->update(['name' => $name]);
        }

        if (array_key_exists('company_website', $data)) {
            $company->website = $data['company_website'] ?: $company->website;
        }
        if (! empty($data['remove_company_logo']) && $company->logo_path) {
            $old = $company->logo_path;
            $company->logo_path = null;
            DB::afterCommit(fn () => Storage::disk('public')->delete($old));
        }
        if ($logo) {
            $old = $company->logo_path;
            $company->logo_path = $stored[] = $this->assets->storeImageFile($logo, 'companies/'.$company->ulid, 1200, 'company_logo')['path'];
            if ($old) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($old));
            }
        }
        $company->save();
        $customer->company()->associate($company);
    }

    private function companySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 2;
        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
