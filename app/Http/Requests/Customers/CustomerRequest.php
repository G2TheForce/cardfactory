<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for creating and updating customers (web form and API). */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        // Drop empty repeater rows so they don't fail validation.
        foreach (['custom_fields' => 'label', 'custom_links' => 'url'] as $key => $required) {
            if (is_array($this->input($key))) {
                $this->merge([$key => array_values(array_filter($this->input($key), fn ($row) => is_array($row) && trim((string) ($row[$required] ?? '')) !== ''))]);
            }
        }
        if (is_string($this->input('business.services'))) {
            $services = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $this->input('business.services')))));
            $this->merge(['business' => array_merge($this->input('business', []), ['services' => $services])]);
        }
    }

    public const URL_RULE = ['nullable', 'string', 'max:255', 'url:http,https'];

    public const PHONE_RULE = ['nullable', 'string', 'max:40', 'regex:/^[0-9+().\-\s]{3,40}$/'];

    public const PHONE_MESSAGE = 'Use digits, spaces and + ( ) - only.';

    public function rules(): array
    {
        $staff = $this->user()->can('customers.manage');
        [$url, $phone] = [self::URL_RULE, self::PHONE_RULE];

        return [
            'full_name' => [...($this->isMethod('patch') ? ['sometimes'] : []), 'required', 'string', 'max:120'],
            'professional_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000'],
            'remove_photo' => ['nullable', 'boolean'],

            'mobile' => $phone,
            'whatsapp' => $phone,
            'office_phone' => $phone,
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'website' => $url,

            'country' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'string', 'max:1000', 'url:https'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'company_name' => ['nullable', 'string', 'max:120'],
            'company_website' => $url,
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'remove_company_logo' => ['nullable', 'boolean'],

            'social_links' => ['nullable', 'array'],
            'social_links.*' => $url,

            'business' => ['nullable', 'array'],
            'business.description' => ['nullable', 'string', 'max:1000'],
            'business.services' => ['nullable', 'array', 'max:20'],
            'business.services.*' => ['string', 'max:80'],
            'business.hours' => ['nullable', 'array'],
            'business.hours.*' => ['nullable', 'string', 'max:60'],

            'custom_fields' => ['nullable', 'array', 'max:'.CustomerFields::MAX_CUSTOM],
            'custom_fields.*.label' => ['required', 'string', 'max:60'],
            'custom_fields.*.value' => ['nullable', 'string', 'max:255'],
            'custom_links' => ['nullable', 'array', 'max:'.CustomerFields::MAX_CUSTOM],
            'custom_links.*.label' => ['nullable', 'string', 'max:60'],
            'custom_links.*.url' => ['required', 'string', 'max:255', 'url:http,https'],

            'introduction' => ['nullable', 'string', 'max:2000'],
            'portfolio_url' => $url,
            'booking_url' => $url,

            'visibility' => ['nullable', 'array'],
            'visibility.card' => ['nullable', 'array'],
            'visibility.card.*' => ['boolean'],
            'visibility.profile' => ['nullable', 'array'],
            'visibility.profile.*' => ['boolean'],

            // Staff only
            'status' => [$staff ? 'nullable' : 'prohibited', Rule::in(array_keys(CustomerFields::STATUSES))],
            'assigned_designer' => [$staff ? 'nullable' : 'prohibited', 'string', Rule::exists('users', 'ulid')],
            'create_login' => [$staff ? 'nullable' : 'prohibited', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.regex' => self::PHONE_MESSAGE,
            'social_links.*.url' => 'Enter a full link starting with https://',
            'custom_links.*.url.url' => 'Enter a full link starting with https://',
        ];
    }

    public function attributes(): array
    {
        return [
            'custom_fields.*.label' => 'field label',
            'custom_links.*.url' => 'link',
            'business.description' => 'business description',
        ];
    }

    /** The validated designer, if any, who must hold designer permissions. */
    public function designer(): ?User
    {
        $ulid = $this->validated('assigned_designer');

        return $ulid ? User::where('ulid', $ulid)->first() : null;
    }
}
