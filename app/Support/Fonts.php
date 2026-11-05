<?php

namespace App\Support;

class Fonts
{
    public static function isGoogle(string $family): bool
    {
        return array_key_exists($family, config('fonts.google'));
    }

    /** CSS font-family stack with a sensible generic fallback. */
    public static function stack(string $family): string
    {
        $category = config("fonts.google.$family.category", 'sans-serif');
        $fallback = config("fonts.fallbacks.$category", 'sans-serif');

        return "'".str_replace("'", '', $family)."', ".$fallback;
    }

    /** Google Fonts CSS2 URL for the given families (unknown families are ignored). */
    public static function googleUrl(array $families): ?string
    {
        $parts = collect($families)->unique()->filter(fn ($f) => self::isGoogle($f))->sort()->map(function ($family) {
            $weights = config("fonts.google.$family.weights", [400]);

            return 'family='.str_replace(' ', '+', $family).':wght@'.implode(';', $weights);
        });

        return $parts->isEmpty() ? null : 'https://fonts.googleapis.com/css2?'.$parts->implode('&').'&display=swap';
    }

    public static function options(): array
    {
        return collect(config('fonts.google'))->map(fn ($meta, $name) => ['name' => $name, 'category' => $meta['category'], 'weights' => $meta['weights']])->values()->all();
    }
}
