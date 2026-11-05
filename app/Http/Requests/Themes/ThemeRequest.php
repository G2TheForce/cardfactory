<?php

namespace App\Http\Requests\Themes;

use App\Models\CardTheme;
use App\Support\Scene\Scene;
use App\Support\Themes\ThemeLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a theme in the admin theme editor. */
class ThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $theme = $this->route('theme');

        return $theme ? $this->user()->can('update', $theme) : $this->user()->can('create', CardTheme::class);
    }

    protected function prepareForValidation(): void
    {
        // Scenes arrive as JSON strings from the editor textareas.
        foreach (['front', 'back'] as $side) {
            if (is_string($this->input($side))) {
                $decoded = json_decode($this->input($side), true);
                $this->merge([$side => is_array($decoded) ? $decoded : '__invalid__']);
            }
        }
        $this->merge(['is_premium' => $this->boolean('is_premium')]);
    }

    public function rules(): array
    {
        $color = ['required', 'string', 'regex:/^#([0-9a-fA-F]{6})$/'];
        $fonts = array_keys(config('fonts.google'));

        return [
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', Rule::in(array_keys(ThemeLibrary::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:500'],
            'is_premium' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'orientation' => ['required', Rule::in(['landscape', 'portrait'])],
            'width_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'height_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'palette' => ['required', 'array'],
            ...collect(Scene::PALETTE_KEYS)->mapWithKeys(fn ($k) => ["palette.$k" => $color])->all(),
            'fonts.heading' => ['required', Rule::in($fonts)],
            'fonts.body' => ['required', Rule::in($fonts)],
            'layout.bleed_mm' => ['required', 'numeric', 'min:0', 'max:10'],
            'layout.safe_mm' => ['required', 'numeric', 'min:0', 'max:15'],
            'layout.corner_radius_mm' => ['required', 'numeric', 'min:0', 'max:10'],
            'front' => ['sometimes', 'array'],
            'back' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'front.array' => 'The front side must be valid scene JSON.',
            'back.array' => 'The back side must be valid scene JSON.',
            'palette.*.regex' => 'Colours must be 6-digit hex values like #1a2b3c.',
        ];
    }
}
