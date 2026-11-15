<?php

namespace App\Support\LandingPages;

use App\Support\ProfileThemes;

/**
 * Look and feel of a landing page: a palette (primary + accent colour and a
 * light, tinted or dark ground), a font pairing and a button shape. resolve()
 * turns the saved choices into the colour tokens the public page uses.
 */
class Styles
{
    public const PALETTES = [
        'midnight' => ['name' => 'Midnight', 'primary' => '#1e293b', 'accent' => '#6366f1', 'mode' => 'light'],
        'ocean' => ['name' => 'Ocean', 'primary' => '#0c4a6e', 'accent' => '#0ea5e9', 'mode' => 'tinted'],
        'forest' => ['name' => 'Forest', 'primary' => '#14532d', 'accent' => '#16a34a', 'mode' => 'tinted'],
        'terracotta' => ['name' => 'Terracotta', 'primary' => '#7c2d12', 'accent' => '#ea580c', 'mode' => 'tinted'],
        'rose' => ['name' => 'Rose', 'primary' => '#881337', 'accent' => '#e11d48', 'mode' => 'light'],
        'gold' => ['name' => 'Noir & gold', 'primary' => '#0a0a0a', 'accent' => '#c8963e', 'mode' => 'dark'],
        'aurora' => ['name' => 'Aurora', 'primary' => '#0b1020', 'accent' => '#22d3ee', 'mode' => 'dark'],
        'mono' => ['name' => 'Monochrome', 'primary' => '#111827', 'accent' => '#111827', 'mode' => 'light'],
    ];

    public const MODES = ['light' => 'Light', 'tinted' => 'Tinted', 'dark' => 'Dark'];

    public const FONTS = [
        'modern' => ['label' => 'Modern', 'heading' => "'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif", 'body' => "'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif"],
        'editorial' => ['label' => 'Editorial', 'heading' => "'Fraunces Variable', 'Fraunces', Georgia, serif", 'body' => "'Manrope Variable', 'Manrope', ui-sans-serif, system-ui, sans-serif"],
        'elegant' => ['label' => 'Elegant', 'heading' => "'Fraunces Variable', 'Fraunces', Georgia, serif", 'body' => "'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif"],
        'friendly' => ['label' => 'Friendly', 'heading' => "'Manrope Variable', 'Manrope', ui-sans-serif, system-ui, sans-serif", 'body' => "'Manrope Variable', 'Manrope', ui-sans-serif, system-ui, sans-serif"],
        'classic' => ['label' => 'Classic', 'heading' => "Georgia, 'Times New Roman', serif", 'body' => "Georgia, 'Times New Roman', serif"],
    ];

    public const BUTTONS = ['rounded' => 'Rounded', 'pill' => 'Pill', 'square' => 'Square'];

    public const RADII = ['rounded' => '12px', 'pill' => '999px', 'square' => '4px'];

    public static function defaults(string $palette = 'midnight'): array
    {
        $p = self::PALETTES[$palette] ?? self::PALETTES['midnight'];

        return ['palette' => $palette, 'primary' => $p['primary'], 'accent' => $p['accent'], 'mode' => $p['mode'], 'font' => 'modern', 'buttons' => 'rounded', 'animations' => true];
    }

    /** Keep only known choices and valid #rrggbb colours. */
    public static function sanitize(array $style): array
    {
        $base = self::defaults(isset(self::PALETTES[$style['palette'] ?? '']) ? $style['palette'] : 'midnight');
        $hex = fn ($v) => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : null;

        return [
            'palette' => $base['palette'],
            'primary' => $hex($style['primary'] ?? null) ?? $base['primary'],
            'accent' => $hex($style['accent'] ?? null) ?? $base['accent'],
            'mode' => isset(self::MODES[$style['mode'] ?? '']) ? $style['mode'] : $base['mode'],
            'font' => isset(self::FONTS[$style['font'] ?? '']) ? $style['font'] : $base['font'],
            'buttons' => isset(self::BUTTONS[$style['buttons'] ?? '']) ? $style['buttons'] : $base['buttons'],
            'animations' => array_key_exists('animations', $style) ? filter_var($style['animations'], FILTER_VALIDATE_BOOL) : true,
        ];
    }

    /**
     * Colour tokens for the public page.
     *
     * @return array{primary: string, accent: string, on_accent: string, on_primary: string, bg: string, surface: string, alt: string, text: string, muted: string, border: string, dark: bool, heading_font: string, body_font: string, radius: string, animations: bool}
     */
    public static function resolve(array $style): array
    {
        $s = self::sanitize($style);
        $mix = fn ($a, $b, $x) => ProfileThemes::mix($a, $b, $x);
        $primary = $s['primary'];

        $colors = match ($s['mode']) {
            'dark' => [
                'bg' => ProfileThemes::isDark($primary) ? $mix($primary, '#000000', 0.35) : $mix($primary, '#05070d', 0.88),
                'text' => '#f4f4f5', 'muted' => '#a1a1aa',
            ],
            'tinted' => ['bg' => $mix($primary, '#ffffff', 0.95), 'text' => '#0f172a', 'muted' => '#5b6474'],
            default => ['bg' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b'],
        };
        $dark = $s['mode'] === 'dark';
        $colors['surface'] = $dark ? $mix($colors['bg'], '#ffffff', 0.06) : '#ffffff';
        $colors['alt'] = $dark ? $mix($colors['bg'], '#ffffff', 0.03) : $mix($primary, '#ffffff', $s['mode'] === 'tinted' ? 0.9 : 0.96);
        $colors['border'] = $dark ? $mix($colors['bg'], '#ffffff', 0.14) : $mix($colors['bg'], '#000000', 0.09);
        // Dark grounds need a primary that reads as a band, not as the page.
        $band = $dark ? $mix($s['accent'], $colors['bg'], 0.55) : $primary;

        return $colors + [
            'primary' => $band,
            'accent' => $s['accent'],
            'on_accent' => ProfileThemes::isDark($s['accent']) ? '#ffffff' : '#0f172a',
            'on_primary' => ProfileThemes::isDark($band) ? '#ffffff' : '#0f172a',
            'dark' => $dark,
            'heading_font' => self::FONTS[$s['font']]['heading'],
            'body_font' => self::FONTS[$s['font']]['body'],
            'radius' => self::RADII[$s['buttons']],
            'animations' => $s['animations'],
        ];
    }
}
