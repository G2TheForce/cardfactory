<?php

namespace App\Http\Resources;

use App\Models\CardTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CardTheme */
class CardThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'is_premium' => $this->is_premium,
            'is_system' => $this->is_system,
            'is_favorite' => (bool) ($this->is_favorite ?? false),
            'size' => ['width_mm' => (float) $this->width_mm, 'height_mm' => (float) $this->height_mm, 'orientation' => $this->orientation],
            'palette' => $this->palette,
            'fonts' => $this->fonts,
            'layout' => $this->layout,
            'preview' => [
                'front_svg' => route('themes.preview.svg', [$this->resource, 'front']),
                'back_svg' => route('themes.preview.svg', [$this->resource, 'back']),
            ],
            'front' => $this->when($request->routeIs('api.v1.themes.show'), $this->front),
            'back' => $this->when($request->routeIs('api.v1.themes.show'), $this->back),
            'based_on' => $this->whenLoaded('parent', fn () => $this->parent?->ulid),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
