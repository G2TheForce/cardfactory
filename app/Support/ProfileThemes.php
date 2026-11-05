<?php

namespace App\Support;

/**
 * Digital profile themes and layout sections. Profile themes are separate from
 * printed card themes: they style the public mobile page only.
 */
final class ProfileThemes
{
    /**
     * header: cover (coloured banner with overlapping photo), centered (no banner),
     * hero (full-bleed gradient behind the header).
     */
    public const THEMES = [
        'classic' => [
            'name' => 'Classic', 'description' => 'Clean white cards under a colour banner.',
            'header' => 'cover', 'font' => 'sans', 'accent' => '#4f46e5',
            'colors' => ['bg' => '#f3f4f6', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280', 'border' => '#e5e7eb'],
        ],
        'minimal' => [
            'name' => 'Minimal', 'description' => 'Plain and centred, lots of white space.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#111827',
            'colors' => ['bg' => '#ffffff', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280', 'border' => '#e5e7eb'],
        ],
        'bold' => [
            'name' => 'Bold', 'description' => 'A full gradient header that makes a statement.',
            'header' => 'hero', 'font' => 'sans', 'accent' => '#db2777',
            'colors' => ['bg' => '#fdf2f8', 'surface' => '#ffffff', 'text' => '#1f2937', 'muted' => '#6b7280', 'border' => '#fbcfe8'],
        ],
        'midnight' => [
            'name' => 'Midnight', 'description' => 'Dark mode with a bright accent.',
            'header' => 'cover', 'font' => 'sans', 'accent' => '#38bdf8',
            'colors' => ['bg' => '#0b1020', 'surface' => '#141b30', 'text' => '#e5e7eb', 'muted' => '#94a3b8', 'border' => '#1f2a44'],
        ],
        'elegant' => [
            'name' => 'Elegant', 'description' => 'Warm paper tones with serif headings.',
            'header' => 'centered', 'font' => 'serif', 'accent' => '#a16207',
            'colors' => ['bg' => '#f7f3ec', 'surface' => '#fffdf9', 'text' => '#2b2620', 'muted' => '#7c7268', 'border' => '#e7dfd2'],
        ],
    ];

    public const SECTIONS = [
        'actions' => 'Quick actions (call, WhatsApp, email, save contact)',
        'ctas' => 'Custom buttons',
        'about' => 'About',
        'contact' => 'Contact details',
        'social' => 'Social media',
        'links' => 'Website and links',
        'business' => 'Services and opening hours',
        'portfolio' => 'Portfolio',
        'map' => 'Location map',
    ];

    public const BUTTON_STYLES = ['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square'];

    public const MAX_CTAS = 6;

    public const MAX_PORTFOLIO = 12;

    public static function get(?string $key): array
    {
        return self::THEMES[$key] ?? self::THEMES['classic'];
    }

    /** Default section order with every section switched on. */
    public static function defaultLayout(): array
    {
        return array_map(fn ($key) => ['key' => $key, 'enabled' => true], array_keys(self::SECTIONS));
    }

    /**
     * Normalise a stored or submitted layout: known keys only, no duplicates,
     * and any section missing from it appended (enabled) at the end.
     */
    public static function normaliseLayout(?array $layout): array
    {
        $out = [];
        foreach ($layout ?? [] as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;
            if (is_string($key) && isset(self::SECTIONS[$key]) && ! isset($out[$key])) {
                $out[$key] = ['key' => $key, 'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOL)];
            }
        }
        foreach (array_keys(self::SECTIONS) as $key) {
            $out[$key] ??= ['key' => $key, 'enabled' => true];
        }

        return array_values($out);
    }
}
