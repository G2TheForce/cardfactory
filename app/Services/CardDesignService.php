<?php

namespace App\Services;

use App\Models\CardDesign;
use App\Models\CardTheme;
use App\Models\Customer;
use App\Models\User;
use App\Support\Scene\Scene;
use App\Support\Scene\SceneValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Card designs: creation from themes, saving editor state (validated scene
 * JSON mirrored into card_elements rows), duplication and rendering with the
 * merge data of the attached customer.
 */
class CardDesignService
{
    public const STATUSES = ['draft', 'final', 'archived'];

    /** Common printed card sizes (landscape mm). */
    public const SIZES = [
        'iso' => ['label' => 'Standard (ISO/CR80) 85.6 × 54 mm', 'width' => 85.6, 'height' => 54.0],
        'us' => ['label' => 'US business card 88.9 × 50.8 mm', 'width' => 88.9, 'height' => 50.8],
        'eu' => ['label' => 'European 85 × 55 mm', 'width' => 85.0, 'height' => 55.0],
        'jp' => ['label' => 'Japan 91 × 55 mm', 'width' => 91.0, 'height' => 55.0],
        'square' => ['label' => 'Square 65 × 65 mm', 'width' => 65.0, 'height' => 65.0],
    ];

    public function __construct(
        private readonly SceneValidator $validator,
        private readonly CardRenderingService $renderer,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name: string, theme?: ?CardTheme, customer?: ?Customer, size?: string, orientation?: string,
     *     bleed_mm?: float, corner_radius_mm?: float, designer_id?: ?int}  $data
     */
    public function create(User $user, array $data): CardDesign
    {
        return DB::transaction(function () use ($user, $data) {
            /** @var CardTheme|null $theme */
            $theme = $data['theme'] ?? null;
            $size = self::SIZES[$data['size'] ?? 'iso'] ?? self::SIZES['iso'];
            $portrait = ($data['orientation'] ?? 'landscape') === 'portrait';
            [$w, $h] = [$size['width'], $size['height']];

            $front = $theme?->front ?? Scene::emptySide();
            $back = $theme?->back ?? Scene::emptySide();
            if ($theme) {
                $w = (float) $theme->width_mm;
                $h = (float) $theme->height_mm;
                $portrait = $theme->orientation === 'portrait' || $h > $w;
                [$w, $h] = $portrait ? [min($w, $h), max($w, $h)] : [max($w, $h), min($w, $h)];
            } elseif ($portrait) {
                [$w, $h] = [$h, $w];
            }

            $design = CardDesign::create([
                'user_id' => $user->id,
                'customer_id' => ($data['customer'] ?? null)?->id,
                'card_theme_id' => $theme?->id,
                'designer_id' => $data['designer_id'] ?? ($user->isStaff() ? $user->id : null),
                'name' => $data['name'],
                'status' => 'draft',
                'width_mm' => $w,
                'height_mm' => $h,
                'bleed_mm' => $data['bleed_mm'] ?? config('smartcard.card.bleed_mm'),
                'corner_radius_mm' => $data['corner_radius_mm'] ?? (float) ($theme?->layout['corner_radius_mm'] ?? 0),
                'orientation' => $portrait ? 'portrait' : 'landscape',
                'front' => $this->validator->validate($front, 'front'),
                'back' => $this->validator->validate($back, 'back'),
                'settings' => [
                    'palette' => $theme?->palette ?? [],
                    'fonts' => $theme?->fonts ?? ['heading' => 'Inter', 'body' => 'Inter'],
                    'show_guides' => true,
                ],
                'version' => 1,
            ]);

            $theme?->increment('usage_count');
            $this->syncElements($design);
            $this->logger->log('design.created', $design, "Created card design \"{$design->name}\"");

            return $design;
        });
    }

    /**
     * Save editor state. Scenes are validated and normalised before storage.
     * Pass the version the client loaded to detect concurrent edits.
     *
     * @throws ValidationException
     */
    public function update(CardDesign $design, array $data, ?int $expectedVersion = null): CardDesign
    {
        if ($expectedVersion !== null && $expectedVersion !== (int) $design->version) {
            throw ValidationException::withMessages([
                'version' => 'This design was changed somewhere else (version '.$design->version.'). Reload to get the latest copy.',
            ])->status(409);
        }

        return DB::transaction(function () use ($design, $data) {
            $attributes = Arr::only($data, ['name', 'status', 'customer_id', 'designer_id', 'bleed_mm', 'corner_radius_mm']);

            foreach (['front', 'back'] as $side) {
                if (array_key_exists($side, $data)) {
                    $attributes[$side] = $this->validator->validate($data[$side], $side);
                }
            }

            if (isset($data['width_mm'], $data['height_mm'])) {
                $attributes['width_mm'] = $data['width_mm'];
                $attributes['height_mm'] = $data['height_mm'];
                $attributes['orientation'] = $data['height_mm'] > $data['width_mm'] ? 'portrait' : 'landscape';
            }

            if (isset($data['palette']) || isset($data['settings'])) {
                $settings = $design->settings ?? [];
                if (isset($data['palette'])) {
                    $settings['palette'] = array_merge($settings['palette'] ?? [], Arr::only($data['palette'], Scene::PALETTE_KEYS));
                }
                foreach (['show_guides', 'snap'] as $flag) {
                    if (isset($data['settings'][$flag])) {
                        $settings[$flag] = (bool) $data['settings'][$flag];
                    }
                }
                $attributes['settings'] = $settings;
            }

            $contentChanged = array_intersect_key($attributes, array_flip(['front', 'back', 'settings', 'width_mm', 'height_mm', 'corner_radius_mm', 'bleed_mm'])) !== [];
            $design->fill($attributes);
            if ($design->isDirty()) {
                $design->version = (int) $design->version + 1;
                $design->save();
                if ($contentChanged) {
                    $this->syncElements($design);
                }
            }

            return $design->refresh();
        });
    }

    public function duplicate(CardDesign $design, User $user, ?string $name = null): CardDesign
    {
        return DB::transaction(function () use ($design, $user, $name) {
            $copy = $design->replicate(['ulid', 'front_thumbnail_path', 'back_thumbnail_path']);
            $copy->ulid = null;
            $copy->user_id = $design->user_id === $user->id || ! $user->isStaff() ? $user->id : $design->user_id;
            $copy->name = $name ?: Str::limit($design->name, 240, '').' (copy)';
            $copy->status = 'draft';
            $copy->version = 1;
            $copy->save();

            $this->syncElements($copy);
            $this->logger->log('design.duplicated', $copy, "Duplicated \"{$design->name}\"");

            return $copy;
        });
    }

    public function delete(CardDesign $design): void
    {
        $this->logger->log('design.deleted', $design, "Deleted card design \"{$design->name}\"");
        $design->delete();
    }

    /** Mirror the scene elements into card_elements rows (used for querying and exports). */
    public function syncElements(CardDesign $design): void
    {
        $design->elements()->delete();
        $now = now();
        $rows = [];
        foreach (['front', 'back'] as $side) {
            foreach (($design->{$side}['elements'] ?? []) as $z => $el) {
                $rows[] = [
                    'ulid' => (string) Str::ulid(),
                    'card_design_id' => $design->id,
                    'side' => $side,
                    'type' => $el['type'],
                    'name' => $el['name'] ?? null,
                    'properties' => json_encode($el),
                    'z_index' => $z,
                    'is_locked' => (bool) ($el['locked'] ?? false),
                    'is_hidden' => (bool) ($el['hidden'] ?? false),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('card_elements')->insert($chunk);
        }
    }

    /** Merge-field values for the design: the attached customer, otherwise sample data. */
    public function mergeData(CardDesign $design): array
    {
        $customer = $design->customer;
        if (! $customer) {
            return Scene::sampleData();
        }

        return $this->customerData($customer);
    }

    public function customerData(Customer $customer): array
    {
        $customer->loadMissing('company');
        $company = $customer->company;
        $name = trim($customer->full_name);
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
        $profile = $customer->digitalProfiles()->where('is_active', true)->latest('id')->first();
        $profileUrl = $profile && Route::has('profiles.public')
            ? route('profiles.public', $profile->slug)
            : rtrim(config('app.url'), '/').'/card/'.($profile->slug ?? Str::slug($name));

        return [
            'full_name' => $name,
            'first_name' => $parts[0] ?? '',
            'professional_title' => (string) $customer->professional_title,
            'designation' => (string) ($customer->designation ?: $customer->professional_title),
            'department' => (string) $customer->department,
            'company' => (string) ($company?->name ?? ($customer->business['company_name'] ?? '')),
            'mobile' => (string) $customer->mobile,
            'whatsapp' => (string) ($customer->whatsapp ?: $customer->mobile),
            'office_phone' => (string) $customer->office_phone,
            'email' => (string) $customer->email,
            'website' => (string) preg_replace('#^https?://#', '', (string) ($customer->website ?: $company?->website)),
            'address' => (string) ($customer->office_address ?: $company?->address),
            'city' => (string) ($customer->city ?: $company?->city),
            'country' => (string) ($customer->country ?: $company?->country),
            'tagline' => (string) ($customer->business['description'] ?? $company?->description ?? ''),
            'profile_url' => $profileUrl,
            'logo' => $company?->logo_path ? Storage::disk('public')->url($company->logo_path) : null,
            'photo' => $customer->profile_photo_path ? Storage::disk('public')->url($customer->profile_photo_path) : null,
            'initials' => $initials,
        ];
    }

    /** Merge data that renders every field as its raw {{tag}} (for template editing). */
    public function rawFieldData(): array
    {
        return collect(Scene::MERGE_FIELDS)->keys()
            ->mapWithKeys(fn ($k) => [$k => in_array($k, ['logo', 'photo'], true) ? null : '{{'.$k.'}}'])
            ->put('profile_url', rtrim(config('app.url'), '/').'/card/preview')
            ->all();
    }

    public function renderOptions(CardDesign $design, array $extra = []): array
    {
        return array_merge([
            'width_mm' => (float) $design->width_mm,
            'height_mm' => (float) $design->height_mm,
            'bleed_mm' => 0,
            'corner_radius_mm' => (float) $design->corner_radius_mm,
            'palette' => $design->settings['palette'] ?? [],
            'data' => $this->mergeData($design),
            'clip_corners' => true,
        ], $extra);
    }

    /** Cached front/back SVG thumbnails for listings. */
    public function preview(CardDesign $design): array
    {
        return Cache::remember("design-preview:{$design->ulid}:{$design->version}:".$design->updated_at?->timestamp.':'.$design->customer?->updated_at?->timestamp, now()->addDay(), function () use ($design) {
            $options = $this->renderOptions($design);

            return [
                'front' => $this->renderer->render($design->front ?? Scene::emptySide(), $options + ['title' => $design->name.' front']),
                'back' => $this->renderer->render($design->back ?? Scene::emptySide(), $options + ['title' => $design->name.' back']),
                'fonts' => $this->renderer->fontsUsed($design->front ?? [], $design->back ?? []),
            ];
        });
    }
}
