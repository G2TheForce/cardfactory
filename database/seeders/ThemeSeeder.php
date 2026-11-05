<?php

namespace Database\Seeders;

use App\Models\CardTheme;
use App\Support\Scene\SceneValidator;
use App\Support\Themes\ThemeLibrary;
use Illuminate\Database\Seeder;

/**
 * Seeds (or refreshes) the system theme library. Idempotent: system themes
 * are matched by slug; user themes are never touched.
 */
class ThemeSeeder extends Seeder
{
    public function run(SceneValidator $validator): void
    {
        foreach (ThemeLibrary::all() as $theme) {
            CardTheme::withTrashed()->updateOrCreate(
                ['slug' => $theme['slug']],
                array_merge($theme, [
                    'front' => $validator->validate($theme['front'], 'front'),
                    'back' => $validator->validate($theme['back'], 'back'),
                    'is_system' => true,
                    'status' => CardTheme::STATUS_PUBLISHED,
                    'published_at' => now(),
                    'deleted_at' => null,
                ]),
            );
        }
    }
}
