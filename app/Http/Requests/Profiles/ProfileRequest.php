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
        foreach (['accent', 'background'] as $key) {
            if (is_string($this->input($key))) {
                $merge[$key] = strtolower(trim($this->input($key)));
            }
        }
        if (is_array($this->input('social_manual'))) {
            // "12,400" and "12.4k" are how people write counts.
            $merge['social_manual'] = array_map(fn ($v) => is_string($v) ? self::parseCount($v) : $v, $this->input('social_manual'));
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
            'background' => ['sometimes', 'nullable', 'regex:/^#[0-9a-f]{6}$/'],
            'header_style' => ['sometimes', 'nullable', Rule::in(array_keys(ProfileThemes::HEADER_STYLES))],
            'font' => ['sometimes', 'nullable', Rule::in(array_keys(ProfileThemes::FONTS))],
            'social_style' => ['sometimes', 'nullable', Rule::in(array_keys(ProfileThemes::SOCIAL_STYLES))],
            'show_follower_counts' => ['sometimes', 'boolean'],
            'social_manual' => ['sometimes', 'array'],
            'social_manual.*' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
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
            'background.regex' => 'Pick a colour like #faf5ff.',
            'social_manual.*.integer' => 'Follower counts must be numbers, like 12400 or 12.4K.',
            'ctas.*.url.url' => 'Button links must start with http:// or https://.',
            'ctas.*.label.required' => 'Give every button a label.',
            'ctas.*.url.required' => 'Give every button a link.',
        ];
    }

    /** "12,400" → 12400, "12.4k" → 12400, "1.2M" → 1200000. Anything else is left for validation to reject. */
    private static function parseCount(string $value): string|int|null
    {
        $v = strtolower(str_replace([',', ' ', '_'], '', trim($value)));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)([kmb])$/', $v, $m)) {
            return (int) round((float) $m[1] * ['k' => 1_000, 'm' => 1_000_000, 'b' => 1_000_000_000][$m[2]]);
        }

        return ctype_digit($v) ? (int) $v : $value;
    }

    public function attributes(): array
    {
        return ['social_manual.*' => 'follower count', 'ctas.*.label' => 'button label', 'ctas.*.url' => 'button link', 'portfolio.*' => 'portfolio image'];
    }
}
