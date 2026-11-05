<?php

namespace Database\Factories;

use App\Models\CardTheme;
use App\Support\Scene\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CardTheme> */
class CardThemeFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'category' => 'minimal',
            'status' => 'published',
            'palette' => ['primary' => '#111827', 'secondary' => '#374151', 'accent' => '#4f46e5', 'background' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280'],
            'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            'front' => Scene::emptySide(),
            'back' => Scene::emptySide('#111827'),
            'layout' => ['bleed_mm' => 3, 'safe_mm' => 3, 'corner_radius_mm' => 0],
        ];
    }
}
