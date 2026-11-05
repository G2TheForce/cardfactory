<?php

namespace App\Http\Requests\Themes;

use App\Support\Scene\Scene;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Palette / font / corner customisation from the theme customiser. */
class CustomizeThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $theme = $this->route('theme');

        // Saving changes in place needs update rights; everything else only needs to be able to use the theme.
        return $this->isMethod('put') ? $this->user()->can('update', $theme) : $this->user()->can('use', $theme);
    }

    public function rules(): array
    {
        $fonts = array_keys(config('fonts.google'));

        return [
            'name' => [$this->isMethod('post') && $this->routeIs('themes.customize.store') ? 'required' : 'nullable', 'string', 'max:80'],
            'palette' => ['nullable', 'array'],
            ...collect(Scene::PALETTE_KEYS)->mapWithKeys(fn ($k) => ["palette.$k" => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6})$/']])->all(),
            'fonts.heading' => ['nullable', Rule::in($fonts)],
            'fonts.body' => ['nullable', Rule::in($fonts)],
            'corner_radius_mm' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    public function customization(): array
    {
        return array_filter($this->validated(), fn ($v) => $v !== null);
    }
}
