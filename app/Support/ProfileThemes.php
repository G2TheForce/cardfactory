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
            'category' => 'professional', 'name' => 'Classic', 'description' => 'Clean white cards under a colour banner.',
            'header' => 'cover', 'font' => 'sans', 'accent' => '#4f46e5',
            'colors' => ['bg' => '#f3f4f6', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280', 'border' => '#e5e7eb'],
        ],
        'minimal' => [
            'category' => 'professional', 'name' => 'Minimal', 'description' => 'Plain and centred, lots of white space.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#111827',
            'colors' => ['bg' => '#ffffff', 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280', 'border' => '#e5e7eb'],
        ],
        'bold' => [
            'category' => 'personal', 'name' => 'Bold', 'description' => 'A full gradient header that makes a statement.',
            'header' => 'hero', 'font' => 'sans', 'accent' => '#db2777',
            'colors' => ['bg' => '#fdf2f8', 'surface' => '#ffffff', 'text' => '#1f2937', 'muted' => '#6b7280', 'border' => '#fbcfe8'],
        ],
        'midnight' => [
            'category' => 'personal', 'name' => 'Midnight', 'description' => 'Dark mode with a bright accent.',
            'header' => 'cover', 'font' => 'sans', 'accent' => '#38bdf8',
            'colors' => ['bg' => '#0b1020', 'surface' => '#141b30', 'text' => '#e5e7eb', 'muted' => '#94a3b8', 'border' => '#1f2a44'],
        ],
        'elegant' => [
            'category' => 'professional', 'name' => 'Elegant', 'description' => 'Warm paper tones with serif headings.',
            'header' => 'centered', 'font' => 'serif', 'accent' => '#a16207',
            'colors' => ['bg' => '#f7f3ec', 'surface' => '#fffdf9', 'text' => '#2b2620', 'muted' => '#7c7268', 'border' => '#e7dfd2'],
        ],
        // Personal: creators, influencers, freelancers. Social buttons come first.
        'creator' => [
            'category' => 'personal', 'name' => 'Creator', 'description' => 'Vivid gradient header for creators and influencers.',
            'header' => 'hero', 'font' => 'sans', 'accent' => '#7c3aed', 'gradient_to' => '#ec4899',
            'colors' => ['bg' => '#faf5ff', 'surface' => '#ffffff', 'text' => '#1e1b4b', 'muted' => '#6b7280', 'border' => '#ede9fe'],
        ],
        'sunset' => [
            'category' => 'personal', 'name' => 'Sunset', 'description' => 'Warm orange-to-pink glow, friendly and bright.',
            'header' => 'hero', 'font' => 'sans', 'accent' => '#f97316', 'gradient_to' => '#e11d48',
            'colors' => ['bg' => '#fff7ed', 'surface' => '#ffffff', 'text' => '#431407', 'muted' => '#9a6b55', 'border' => '#fed7aa'],
        ],
        'neon' => [
            'category' => 'personal', 'name' => 'Neon', 'description' => 'Dark page with electric cyan, made for gamers and streamers.',
            'header' => 'hero', 'font' => 'sans', 'accent' => '#06b6d4', 'gradient_to' => '#a855f7',
            'colors' => ['bg' => '#09090b', 'surface' => '#18181b', 'text' => '#f4f4f5', 'muted' => '#a1a1aa', 'border' => '#27272a'],
        ],
        'aurora' => [
            'category' => 'personal', 'name' => 'Aurora', 'description' => 'Glowing northern-lights backdrop behind frosted glass.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#8b5cf6', 'gradient_to' => '#22d3ee',
            'card_style' => 'glass', 'effect' => 'aurora', 'glow' => true,
            'colors' => ['bg' => '#0b0b1e', 'surface' => '#16163a', 'text' => '#eef2ff', 'muted' => '#a5b4fc', 'border' => '#2e2e5c'],
        ],
        'cyber' => [
            'category' => 'personal', 'name' => 'Cyber', 'description' => 'Neon green on a dark grid. Futuristic and loud.',
            'header' => 'centered', 'font' => 'mono', 'accent' => '#22c55e', 'gradient_to' => '#06b6d4',
            'card_style' => 'outline', 'effect' => 'grid', 'glow' => true,
            'colors' => ['bg' => '#030712', 'surface' => '#0b1220', 'text' => '#e2e8f0', 'muted' => '#94a3b8', 'border' => '#1e293b'],
        ],
        // Professional: consultants, executives, companies.
        'executive' => [
            'category' => 'professional', 'name' => 'Executive', 'description' => 'Navy and serif, quietly confident.',
            'header' => 'cover', 'font' => 'serif', 'accent' => '#1e3a8a', 'gradient_to' => '#0f172a',
            'colors' => ['bg' => '#f8fafc', 'surface' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b', 'border' => '#e2e8f0'],
        ],
        'corporate' => [
            'category' => 'professional', 'name' => 'Corporate', 'description' => 'Trustworthy blue banner with company logo.',
            'header' => 'cover', 'font' => 'sans', 'accent' => '#0369a1', 'gradient_to' => '#0ea5e9',
            'colors' => ['bg' => '#f0f9ff', 'surface' => '#ffffff', 'text' => '#0c4a6e', 'muted' => '#64748b', 'border' => '#e0f2fe'],
        ],
        'glass' => [
            'category' => 'professional', 'name' => 'Glass', 'description' => 'Soft gradient light with frosted glass panels.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#2563eb', 'gradient_to' => '#7c3aed',
            'card_style' => 'glass', 'effect' => 'aurora', 'glow' => false,
            'colors' => ['bg' => '#eef2ff', 'surface' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b', 'border' => '#dbe4ff'],
        ],
        'carbon' => [
            'category' => 'professional', 'name' => 'Carbon', 'description' => 'Sleek dark graphite with a fine grid and gold accent.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#d4a72c', 'gradient_to' => '#78350f',
            'card_style' => 'solid', 'effect' => 'grid', 'glow' => false,
            'colors' => ['bg' => '#0c0c0e', 'surface' => '#17171b', 'text' => '#f4f4f5', 'muted' => '#a1a1aa', 'border' => '#27272a'],
        ],
        'studio' => [
            'category' => 'professional', 'name' => 'Studio', 'description' => 'Graphite and white for designers and agencies.',
            'header' => 'centered', 'font' => 'sans', 'accent' => '#18181b',
            'colors' => ['bg' => '#fafafa', 'surface' => '#ffffff', 'text' => '#18181b', 'muted' => '#71717a', 'border' => '#e4e4e7'],
        ],
        // Profile Studio: full one-page personal websites (layout "studio",
        // rendered by profiles/public-studio). "deep" is the dark band colour.
        'ps-crimson' => self::STUDIO_LIGHT + [
            'name' => 'Himalayan Crimson', 'description' => 'Crimson and midnight navy on warm paper.',
            'accent' => '#a3122a', 'deep' => '#14203f', 'colors' => ['bg' => '#fbf9f6'] + self::STUDIO_INK,
        ],
        'ps-rhododendron' => self::STUDIO_LIGHT + [
            'name' => 'Rhododendron', 'description' => 'Deep rose with plum, soft and confident.',
            'accent' => '#b0204f', 'deep' => '#2a1430', 'colors' => ['bg' => '#fcf8f8'] + self::STUDIO_INK,
        ],
        'ps-tea' => self::STUDIO_LIGHT + [
            'name' => 'Tea Garden', 'description' => 'Forest green, calm and natural.',
            'accent' => '#24644f', 'deep' => '#13261f', 'colors' => ['bg' => '#f7f9f6'] + self::STUDIO_INK,
        ],
        'ps-marigold' => self::STUDIO_LIGHT + [
            'name' => 'Marigold', 'description' => 'Burnt orange and espresso, warm and bold.',
            'accent' => '#9a4a07', 'deep' => '#2b1b0e', 'colors' => ['bg' => '#fcf8f2'] + self::STUDIO_INK,
        ],
        'ps-everest' => self::STUDIO_LIGHT + [
            'name' => 'Everest Blue', 'description' => 'Cobalt and ink, crisp and corporate.',
            'accent' => '#1e4fb8', 'deep' => '#0f1a33', 'colors' => ['bg' => '#f6f8fb'] + self::STUDIO_INK,
        ],
        'ps-graphite' => self::STUDIO_LIGHT + [
            'name' => 'Graphite', 'description' => 'Black and stone, quietly luxurious.',
            'accent' => '#26262e', 'deep' => '#111114', 'colors' => ['bg' => '#f7f6f3'] + self::STUDIO_INK,
        ],
        'ps-night' => self::STUDIO_LIGHT + [
            'name' => 'Night Studio', 'description' => 'The same site in dark mode with a rose glow.',
            'accent' => '#e0475f', 'deep' => '#0a0a0e', 'glow' => true,
            'colors' => ['bg' => '#121216', 'surface' => '#1b1b21', 'text' => '#f1f0ee', 'muted' => '#9a99a3', 'border' => '#2e2e36'],
        ],
    ];

    /** Shared base for Profile Studio themes. */
    private const STUDIO_LIGHT = [
        'category' => 'studio', 'layout' => 'studio', 'header' => 'centered', 'font' => 'serif',
    ];

    private const STUDIO_INK = ['surface' => '#ffffff', 'text' => '#16161c', 'muted' => '#5c5c66', 'border' => '#e6e0d8'];

    public const CATEGORIES = [
        'personal' => ['label' => 'Personal', 'description' => 'Creators, influencers and freelancers. Social buttons come first.'],
        'professional' => ['label' => 'Professional', 'description' => 'Consultants, executives and companies. Contact details come first.'],
        'studio' => ['label' => 'Profile Studio', 'description' => 'A full animated one-page website: arched portrait, count-up stats, motto band and a contact panel. Same contact, social, QR, NFC and analytics features.'],
    ];

    public const SOCIAL_STYLES = ['buttons' => 'Follow buttons', 'icons' => 'Icons only'];

    public const CARD_STYLES = ['solid' => 'Solid', 'glass' => 'Frosted glass', 'outline' => 'Outline'];

    public const EFFECTS = ['none' => 'None', 'aurora' => 'Aurora glow', 'grid' => 'Tech grid', 'dots' => 'Dots', 'mesh' => 'Gradient mesh'];

    public const HEADER_STYLES = ['cover' => 'Banner', 'hero' => 'Full gradient', 'centered' => 'Plain'];

    /** Font stacks. No web fonts are loaded, so these work offline and in exported sites. */
    public const FONTS = [
        'sans' => ['label' => 'Modern', 'stack' => "'Inter', ui-sans-serif, system-ui, sans-serif"],
        'serif' => ['label' => 'Classic serif', 'stack' => "Georgia, 'Times New Roman', serif"],
        'rounded' => ['label' => 'Rounded', 'stack' => "ui-rounded, 'SF Pro Rounded', 'Arial Rounded MT Bold', 'Nunito', system-ui, sans-serif"],
        'mono' => ['label' => 'Mono', 'stack' => "ui-monospace, 'Cascadia Code', 'SF Mono', Menlo, Consolas, monospace"],
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

    /**
     * A theme with the profile's own overrides applied: header style, font and
     * background colour. A custom background gets matching surface, text and
     * border colours so the page stays readable on light and dark backgrounds.
     */
    public static function resolve(?string $key, array $settings): array
    {
        $theme = self::get($key);
        if (isset(self::HEADER_STYLES[$settings['header_style'] ?? ''])) {
            $theme['header'] = $settings['header_style'];
        }
        if (isset(self::FONTS[$settings['font'] ?? ''])) {
            $theme['font'] = $settings['font'];
        }
        $bg = $settings['background'] ?? null;
        if (is_string($bg) && preg_match('/^#[0-9a-f]{6}$/i', $bg)) {
            $theme['colors'] = self::isDark($bg)
                ? ['bg' => $bg, 'surface' => self::mix($bg, '#ffffff', 0.08), 'text' => '#f4f4f5', 'muted' => '#a1a1aa', 'border' => self::mix($bg, '#ffffff', 0.16)]
                : ['bg' => $bg, 'surface' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280', 'border' => self::mix($bg, '#000000', 0.1)];
        }
        $theme['card_style'] = isset(self::CARD_STYLES[$settings['card_style'] ?? '']) ? $settings['card_style'] : ($theme['card_style'] ?? 'solid');
        $theme['effect'] = isset(self::EFFECTS[$settings['effect'] ?? '']) ? $settings['effect'] : ($theme['effect'] ?? 'none');
        $theme['glow'] = isset($settings['glow']) ? (bool) $settings['glow'] : ($theme['glow'] ?? false);
        $theme['dark'] = self::isDark($theme['colors']['bg']);
        $theme['layout'] ??= 'card';
        $theme['font_override'] = isset(self::FONTS[$settings['font'] ?? '']);
        $theme['font_stack'] = self::FONTS[$theme['font']]['stack'] ?? self::FONTS['sans']['stack'];

        return $theme;
    }

    public static function isDark(string $hex): bool
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 128;
    }

    /** Blend $hex towards $with by $amount (0–1). */
    public static function mix(string $hex, string $with, float $amount): string
    {
        $a = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $b = sscanf(ltrim($with, '#'), '%02x%02x%02x');

        return sprintf('#%02x%02x%02x', ...array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $amount), $a, $b));
    }

    /** Profile Studio themes render as a full one-page website. */
    public static function isStudio(?string $key): bool
    {
        return (self::THEMES[$key ?? '']['layout'] ?? null) === 'studio';
    }

    /** Themes grouped by category, in CATEGORIES order. */
    public static function byCategory(): array
    {
        return collect(self::CATEGORIES)->map(fn ($c, $key) => $c + [
            'themes' => array_filter(self::THEMES, fn ($t) => $t['category'] === $key),
        ])->all();
    }

    /**
     * Default section order with every section switched on. Personal themes put
     * the social buttons right under the quick actions.
     */
    public static function defaultLayout(?string $theme = null): array
    {
        $keys = array_keys(self::SECTIONS);
        if (self::isStudio($theme)) {
            // A website reads top to bottom: story first, contact panel last.
            $keys = ['actions', 'about', 'social', 'business', 'links', 'ctas', 'portfolio', 'map', 'contact'];
        } elseif ($theme && self::get($theme)['category'] === 'personal') {
            $keys = array_values(array_unique(['actions', 'social', ...$keys]));
        }

        return array_map(fn ($key) => ['key' => $key, 'enabled' => true], $keys);
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
