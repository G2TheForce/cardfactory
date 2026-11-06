<?php

namespace App\Support\Scene;

use Illuminate\Support\Facades\Cache;

/**
 * Supplies vector icon markup (Lucide, 24x24 stroke icons) for the SVG renderer
 * and the editor, plus social network icons.
 */
class IconLibrary
{
    /** Social network => lucide icon used to draw it. */
    public const SOCIAL = [
        'facebook' => 'facebook',
        'instagram' => 'instagram',
        'linkedin' => 'linkedin',
        'tiktok' => 'music-2',
        'youtube' => 'youtube',
        'x' => 'x',
        'github' => 'github',
        'threads' => 'at-sign',
        'telegram' => 'send',
        'pinterest' => 'pin',
        'snapchat' => 'ghost',
        'twitch' => 'twitch',
        'dribbble' => 'dribbble',
        'behance' => 'palette',
        'whatsapp' => 'message-circle',
        'website' => 'globe',
        'email' => 'mail',
        'phone' => 'phone',
    ];

    /** Curated set offered in the editor's icon picker. */
    public const CURATED = [
        'phone', 'smartphone', 'mail', 'globe', 'map-pin', 'building-2', 'briefcase', 'calendar', 'clock', 'link',
        'message-circle', 'star', 'award', 'scale', 'stethoscope', 'utensils', 'house', 'chart-no-axes-column',
        'code-xml', 'rocket', 'gem', 'shield', 'leaf', 'camera', 'heart', 'sparkles', 'zap', 'user', 'qr-code', 'nfc',
    ];

    public static function directory(): string
    {
        return base_path('vendor/mallardduck/blade-lucide-icons/resources/svg/icons');
    }

    public static function exists(string $name): bool
    {
        return preg_match('/^[a-z0-9-]{1,50}$/', $name) === 1 && is_file(self::directory()."/$name.svg");
    }

    /** Inner SVG markup (paths etc.) for a 24x24 icon, or null. */
    public static function inner(string $name): ?string
    {
        if (! self::exists($name)) {
            return null;
        }

        return Cache::rememberForever("lucide-icon:$name", function () use ($name) {
            $svg = file_get_contents(self::directory()."/$name.svg");

            return trim(preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>\s*$/s'], '', $svg));
        });
    }

    public static function social(string $network): ?string
    {
        return isset(self::SOCIAL[$network]) ? self::inner(self::SOCIAL[$network]) : null;
    }
}
