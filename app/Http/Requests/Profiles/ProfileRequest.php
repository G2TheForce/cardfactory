<?php

namespace App\Http\Requests\Profiles;

use App\Services\DigitalProfileService;
use App\Support\ProfileThemes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for updating a digital profile (web form and API). */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('profile'));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (is_string($this->input('slug'))) {
            $merge['slug'] = strtolower(trim($this->input('slug')));
        }
        if (is_array($this->input('ctas'))) {
            $merge['ctas'] = array_values(array_filter($this->input('ctas'), fn ($c) => is_array($c) && (trim((string) ($c['label'] ?? '')) !== '' || trim((string) ($c['url'] ?? '')) !== '')));
        }
        if (is_string($this->input('accent'))) {
            $merge['accent'] = strtolower(trim($this->input('accent')));
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'required', 'string', 'min:3', 'max:60', 'regex:'.DigitalProfileService::SLUG_PATTERN],
            'profile_theme' => ['sometimes', 'required', Rule::in(array_keys(ProfileThemes::THEMES))],
            'accent' => ['sometimes', 'nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'button_style' => ['sometimes', 'nullable', Rule::in(array_keys(ProfileThemes::BUTTON_STYLES))],
            'headline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'allow_indexing' => ['sometimes', 'boolean'],
            'show_save_contact' => ['sometimes', 'boolean'],
            'show_qr' => ['sometimes', 'boolean'],
            'map_embed' => ['sometimes', 'boolean'],
            'layout' => ['sometimes', 'array', 'max:'.count(ProfileThemes::SECTIONS)],
            'layout.*.key' => ['required', 'distinct', Rule::in(array_keys(ProfileThemes::SECTIONS))],
            'layout.*.enabled' => ['required', 'boolean'],
            'ctas' => ['sometimes', 'array', 'max:'.ProfileThemes::MAX_CTAS],
            'ctas.*.label' => ['required', 'string', 'max:40'],
            'ctas.*.url' => ['required', 'url:http,https', 'max:500'],
            'ctas.*.style' => ['nullable', Rule::in(['solid', 'outline'])],
            'card_design' => ['sometimes', 'nullable', 'string', 'size:26'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
            'portfolio' => ['sometimes', 'array', 'max:'.ProfileThemes::MAX_PORTFOLIO],
            'portfolio.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'portfolio_remove' => ['sometimes', 'array'],
            'portfolio_remove.*' => ['string', 'max:16'],
            'portfolio_captions' => ['sometimes', 'array', 'max:'.ProfileThemes::MAX_PORTFOLIO],
            'portfolio_captions.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and dashes (3–60 characters), starting and ending with a letter or number.',
            'accent.regex' => 'Pick a colour like #4f46e5.',
            'ctas.*.url.url' => 'Button links must start with http:// or https://.',
            'ctas.*.label.required' => 'Give every button a label.',
            'ctas.*.url.required' => 'Give every button a link.',
        ];
    }

    public function attributes(): array
    {
        return ['ctas.*.label' => 'button label', 'ctas.*.url' => 'button link', 'portfolio.*' => 'portfolio image'];
    }
}
