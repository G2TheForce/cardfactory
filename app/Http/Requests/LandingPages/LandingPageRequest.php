<?php

namespace App\Http\Requests\LandingPages;

use App\Services\LandingPageService;
use App\Support\LandingPages\Sections;
use App\Support\LandingPages\Styles;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Saving the landing page editor. The section list arrives as JSON
 * (sections_json) and is cleaned field by field by Sections::sanitize.
 */
class LandingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('landingPage'));
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (is_string($this->input('slug'))) {
            $merge['slug'] = strtolower(trim($this->input('slug')));
        }
        if (is_string($this->input('sections_json'))) {
            $decoded = json_decode($this->input('sections_json'), true);
            $merge['sections'] = is_array($decoded) ? $decoded : 'invalid';
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        // The live preview fires on every keystroke, so half-typed values (a colour,
        // an address) are normal there. The service cleans style and settings anyway;
        // only the section list has to be readable.
        if ($this->isPreview()) {
            return ['sections' => ['sometimes', 'array', 'max:'.Sections::MAX_SECTIONS]];
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'min:3', 'max:60', 'regex:'.LandingPageService::SLUG_PATTERN],
            'sections' => ['sometimes', 'array', 'max:'.Sections::MAX_SECTIONS],
            'style' => ['sometimes', 'array'],
            'style.palette' => ['nullable', Rule::in(array_keys(Styles::PALETTES))],
            'style.primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.mode' => ['nullable', Rule::in(array_keys(Styles::MODES))],
            'style.font' => ['nullable', Rule::in(array_keys(Styles::FONTS))],
            'style.buttons' => ['nullable', Rule::in(array_keys(Styles::BUTTONS))],
            'style.animations' => ['nullable', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.seo_title' => ['nullable', 'string', 'max:70'],
            'settings.seo_description' => ['nullable', 'string', 'max:170'],
            'settings.allow_indexing' => ['nullable', 'boolean'],
            'settings.show_nav' => ['nullable', 'boolean'],
            'settings.nav_cta' => ['nullable', 'string', 'max:30'],
            'settings.footer_text' => ['nullable', 'string', 'max:200'],
            'settings.notify_leads' => ['nullable', 'boolean'],
            'settings.notify_email' => ['nullable', 'email', 'max:190'],
            'settings.logo_path' => ['nullable', 'string', 'max:255'],
            'settings.remove_logo' => ['nullable', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The preview renders inside the designer's iframe. A redirect there would load
     * the whole designer into the iframe, which starts its own preview, and so on
     * forever. Answer with a short message page instead.
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->isPreview()) {
            throw new HttpResponseException(response()->view('landing-pages.preview-error', ['messages' => $validator->errors()->all()], 422));
        }

        parent::failedValidation($validator);
    }

    private function isPreview(): bool
    {
        return $this->routeIs('landing-pages.draft-preview');
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and dashes (3–60 characters).',
            'sections.array' => 'The page sections could not be read. Reload the editor and try again.',
            'style.primary.regex' => 'Use a colour like #1e293b.',
            'style.accent.regex' => 'Use a colour like #6366f1.',
        ];
    }
}
