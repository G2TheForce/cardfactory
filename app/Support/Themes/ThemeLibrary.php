<?php

namespace App\Support\Themes;

use Illuminate\Support\Str;

/**
 * The initial library of professional themes. Each theme is editable scene
 * data (front + back), a palette and a font pairing; nothing is a flattened
 * image. Layout "archetypes" are shared and specialised per theme.
 *
 * Card: 85.6 x 54 mm (landscape). Safe area 3 mm; content starts at ~5 mm.
 */
class ThemeLibrary
{
    private const W = 85.6;

    private const H = 54.0;

    public const CATEGORIES = [
        'corporate' => 'Corporate',
        'minimal' => 'Minimal',
        'luxury' => 'Luxury',
        'modern' => 'Modern',
        'technology' => 'Technology',
        'creative' => 'Creative',
        'real-estate' => 'Real Estate',
        'medical' => 'Medical',
        'legal' => 'Legal',
        'hospitality' => 'Hospitality',
        'consulting' => 'Consulting',
        'personal' => 'Personal Branding',
        'finance' => 'Finance',
        'freelance' => 'Freelance',
        'dark' => 'Dark',
        'elegant' => 'Elegant',
        'classic' => 'Classic',
        'beauty' => 'Beauty & Wellness',
        'fitness' => 'Fitness',
    ];

    public static function all(): array
    {
        $themes = [
            ['Corporate Executive', 'corporate', 'executive', ['primary' => '#0f2747', 'secondary' => '#1e3a5f', 'accent' => '#c9a227', 'background' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b'], ['Montserrat', 'Inter'], ['icon' => 'briefcase']],
            ['Minimalist', 'minimal', 'centered', ['primary' => '#111111', 'secondary' => '#3f3f46', 'accent' => '#111111', 'background' => '#ffffff', 'text' => '#111111', 'muted' => '#a1a1aa'], ['Inter', 'Inter'], ['weight' => 600, 'tracking' => 40]],
            ['Luxury Black and Gold', 'luxury', 'centered', ['primary' => '#0a0a0a', 'secondary' => '#1c1917', 'accent' => '#d4a843', 'background' => '#0a0a0a', 'text' => '#f5e6c4', 'muted' => '#a8946a'], ['Cinzel', 'Cormorant Garamond'], ['frame' => true, 'pattern' => 'diagonal', 'tracking' => 120]],
            ['Modern Gradient', 'modern', 'gradient', ['primary' => '#4f46e5', 'secondary' => '#db2777', 'accent' => '#ffffff', 'background' => '#ffffff', 'text' => '#ffffff', 'muted' => '#e0e7ff'], ['Poppins', 'Poppins'], ['angle' => 135, 'icon' => 'sparkles']],
            ['Technology', 'technology', 'split', ['primary' => '#0b1220', 'secondary' => '#111c33', 'accent' => '#22d3ee', 'background' => '#0f172a', 'text' => '#e2e8f0', 'muted' => '#64748b'], ['Space Grotesk', 'JetBrains Mono'], ['pattern' => 'grid', 'icon' => 'code-xml']],
            ['Creative Agency', 'creative', 'diagonal', ['primary' => '#ff5a36', 'secondary' => '#ffb703', 'accent' => '#1d3557', 'background' => '#fffaf3', 'text' => '#1d3557', 'muted' => '#6b7280'], ['Outfit', 'Outfit'], ['icon' => 'sparkles']],
            ['Real Estate', 'real-estate', 'photo', ['primary' => '#0f5132', 'secondary' => '#198754', 'accent' => '#c8a96a', 'background' => '#ffffff', 'text' => '#0f2a1d', 'muted' => '#6b7d73'], ['Lora', 'Lato'], ['icon' => 'house']],
            ['Medical Professional', 'medical', 'executive', ['primary' => '#0e7490', 'secondary' => '#0891b2', 'accent' => '#14b8a6', 'background' => '#f8fdff', 'text' => '#0c4a6e', 'muted' => '#64748b'], ['Nunito Sans', 'Nunito Sans'], ['icon' => 'stethoscope', 'pattern' => 'circles']],
            ['Lawyer and Legal', 'legal', 'centered', ['primary' => '#1f2a44', 'secondary' => '#7f1d1d', 'accent' => '#7f1d1d', 'background' => '#fbf9f4', 'text' => '#1f2a44', 'muted' => '#78716c'], ['Libre Baskerville', 'Lato'], ['icon' => 'scale', 'frame' => true]],
            ['Restaurant and Hospitality', 'hospitality', 'band', ['primary' => '#7c2d12', 'secondary' => '#9a3412', 'accent' => '#f59e0b', 'background' => '#fff7ed', 'text' => '#431407', 'muted' => '#9a6b4f'], ['Playfair Display', 'Josefin Sans'], ['icon' => 'utensils', 'pattern' => 'waves']],
            ['Business Consultant', 'consulting', 'split', ['primary' => '#1d4ed8', 'secondary' => '#1e40af', 'accent' => '#93c5fd', 'background' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b'], ['Manrope', 'Manrope'], ['icon' => 'chart-no-axes-column', 'light' => true]],
            ['Personal Branding', 'personal', 'photo', ['primary' => '#e76f51', 'secondary' => '#f4a261', 'accent' => '#264653', 'background' => '#fffaf5', 'text' => '#264653', 'muted' => '#7c8b8f'], ['DM Serif Display', 'DM Sans'], ['icon' => 'star']],
            ['Finance and Banking', 'finance', 'executive', ['primary' => '#064e3b', 'secondary' => '#065f46', 'accent' => '#b08d57', 'background' => '#ffffff', 'text' => '#022c22', 'muted' => '#5b6b65'], ['Merriweather', 'Roboto'], ['icon' => 'shield']],
            ['Freelancer', 'freelance', 'band', ['primary' => '#111827', 'secondary' => '#1f2937', 'accent' => '#facc15', 'background' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280'], ['Work Sans', 'Work Sans'], ['icon' => 'zap', 'pattern' => 'dots']],
            ['Digital Entrepreneur', 'modern', 'gradient', ['primary' => '#7c3aed', 'secondary' => '#06b6d4', 'accent' => '#ffffff', 'background' => '#ffffff', 'text' => '#ffffff', 'muted' => '#ede9fe'], ['Sora', 'Sora'], ['angle' => 30, 'icon' => 'rocket']],
            ['Premium Dark', 'dark', 'centered', ['primary' => '#09090b', 'secondary' => '#18181b', 'accent' => '#a78bfa', 'background' => '#09090b', 'text' => '#fafafa', 'muted' => '#a1a1aa'], ['Raleway', 'Raleway'], ['pattern' => 'dots', 'tracking' => 60]],
            ['Elegant White', 'elegant', 'centered', ['primary' => '#3f3a36', 'secondary' => '#57534e', 'accent' => '#b8975a', 'background' => '#ffffff', 'text' => '#292524', 'muted' => '#a8a29e'], ['Cormorant Garamond', 'Lato'], ['frame' => true, 'tracking' => 80]],
            ['Glassmorphism', 'modern', 'glass', ['primary' => '#2563eb', 'secondary' => '#9333ea', 'accent' => '#ffffff', 'background' => '#ffffff', 'text' => '#ffffff', 'muted' => '#dbeafe'], ['Outfit', 'Inter'], ['angle' => 120]],
            ['Modern Colorful', 'creative', 'diagonal', ['primary' => '#06d6a0', 'secondary' => '#118ab2', 'accent' => '#ef476f', 'background' => '#ffffff', 'text' => '#073b4c', 'muted' => '#5c7a86'], ['Poppins', 'Poppins'], ['icon' => 'heart', 'circles' => true]],
            ['Classic Professional', 'classic', 'executive', ['primary' => '#1e293b', 'secondary' => '#334155', 'accent' => '#1e293b', 'background' => '#fdfdfb', 'text' => '#1e293b', 'muted' => '#64748b'], ['EB Garamond', 'Open Sans'], ['icon' => 'award', 'classic' => true]],
        ];

        $library = collect($themes)->map(function ($t, $i) {
            [$name, $category, $archetype, $palette, [$heading, $body], $opts] = $t;
            $fonts = ['heading' => $heading, 'body' => $body];
            [$front, $back] = self::build($archetype, $palette, $fonts, $opts);

            return [
                'name' => $name,
                'slug' => Str::slug($name),
                'category' => $category,
                'description' => self::describe($name, $archetype),
                'palette' => $palette,
                'fonts' => $fonts,
                'front' => $front,
                'back' => $back,
                'layout' => ['archetype' => $archetype, 'bleed_mm' => 3, 'safe_mm' => 3, 'corner_radius_mm' => in_array($archetype, ['gradient', 'glass'], true) ? 3 : 0],
                'is_premium' => in_array($category, ['luxury', 'dark', 'elegant'], true),
                'sort_order' => $i + 1,
            ];
        });

        $premium = collect(PremiumThemes::all())->values()->map(fn ($t, $i) => $t + ['sort_order' => $library->count() + $i + 1]);

        return $library->concat($premium)->all();
    }

    private static function describe(string $name, string $archetype): string
    {
        return match ($archetype) {
            'executive' => "$name: a structured layout with an accent bar, logo and clear contact column.",
            'centered' => "$name: a balanced, centred composition with refined typography.",
            'gradient' => "$name: a vivid gradient front with bold typography and a clean QR back.",
            'split' => "$name: a split-panel layout separating brand and contact details.",
            'diagonal' => "$name: energetic geometric shapes for a creative first impression.",
            'photo' => "$name: puts your profile photo front and centre.",
            'band' => "$name: a warm layout with a contact band and patterned back.",
            'glass' => "$name: frosted-glass panels over a colourful gradient.",
            default => $name,
        };
    }

    // ------------------------------------------------------------------ archetypes

    private static function build(string $archetype, array $p, array $f, array $o): array
    {
        return match ($archetype) {
            'executive' => self::executive($f, $o),
            'centered' => self::centered($p, $f, $o),
            'gradient' => self::gradient($f, $o),
            'split' => self::split($f, $o),
            'diagonal' => self::diagonal($f, $o),
            'photo' => self::photo($f, $o),
            'band' => self::band($f, $o),
            'glass' => self::glass($f, $o),
        };
    }

    private static function executive(array $f, array $o): array
    {
        $classic = $o['classic'] ?? false;
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => array_merge(
                [
                    self::shape('accent-bar', 0, 0, $classic ? 85.6 : 3, $classic ? 1.6 : 54, 'palette.primary', name: 'Accent bar'),
                    self::image('logo', 'logo', 8, 7, 14, 14, name: 'Logo'),
                    self::text('company', '{{company}}', 24, 9, 30, 7, $f['heading'], 700, 'palette.primary', ['letterSpacing' => 60, 'textTransform' => 'uppercase', 'name' => 'Company']),
                    self::text('tagline', '{{tagline}}', 24, 14, 32, 5.5, $f['body'], 400, 'palette.muted', ['name' => 'Tagline']),
                    self::text('name', '{{full_name}}', 8, 30, 40, 11, $f['heading'], 700, 'palette.text', ['name' => 'Name']),
                    self::text('title', '{{professional_title}}', 8, 36.5, 40, 6.5, $f['body'], 500, 'palette.accent', ['name' => 'Title']),
                    self::line('divider', 8, 43, 14, 'palette.accent', 0.6),
                ],
                self::contacts(50, 26.5, 32, ['phone' => '{{mobile}}', 'mail' => '{{email}}', 'globe' => '{{website}}', 'map-pin' => '{{city}}'], $f['body'], 'palette.text', 'palette.accent'),
                isset($o['pattern']) ? [self::pattern('pattern', 62, 0, 23.6, 18, $o['pattern'], 'palette.accent', 0.25)] : [],
            ),
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => 'palette.primary'],
            'elements' => [
                self::image('logo-back', 'logo', 8, 8, 16, 16, name: 'Logo'),
                self::text('company-back', '{{company}}', 8, 28, 45, 12, $f['heading'], 700, '#ffffff', ['name' => 'Company']),
                self::text('tagline-back', '{{tagline}}', 8, 36, 45, 6.5, $f['body'], 400, 'palette.accent', ['name' => 'Tagline']),
                self::qr('qr', 58, 14, 22, 'palette.primary', '#ffffff'),
                self::text('scan', 'Scan or tap to connect', 54, 38.5, 30, 5, $f['body'], 500, '#ffffff', ['align' => 'center', 'name' => 'Call to action']),
                self::icon('nfc-icon', 'nfc', 76.5, 44, 4.5, 'palette.accent'),
            ],
        ];

        return [$front, $back];
    }

    private static function centered(array $p, array $f, array $o): array
    {
        $tracking = $o['tracking'] ?? 0;
        $frame = $o['frame'] ?? false;
        $elements = [];
        if ($frame) {
            $elements[] = self::shape('frame', 4, 4, 77.6, 46, 'transparent', stroke: 'palette.accent', strokeWidth: 0.3, name: 'Frame');
        }
        if (isset($o['pattern'])) {
            $elements[] = self::pattern('pattern', 0, 0, 85.6, 54, $o['pattern'], 'palette.accent', 0.08);
        }
        if (isset($o['icon'])) {
            $elements[] = self::icon('emblem', $o['icon'], 39.3, 9, 7, 'palette.accent');
        }
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => array_merge($elements, [
                self::text('name', '{{full_name}}', 8, 19, 69.6, 13, $f['heading'], $o['weight'] ?? 600, 'palette.text', ['align' => 'center', 'letterSpacing' => $tracking, 'textTransform' => $tracking >= 60 ? 'uppercase' : 'none', 'name' => 'Name']),
                self::text('title', '{{professional_title}}', 8, 27, 69.6, 6.5, $f['body'], 400, 'palette.muted', ['align' => 'center', 'letterSpacing' => 80, 'textTransform' => 'uppercase', 'name' => 'Title']),
                self::line('divider', 36.8, 34, 12, 'palette.accent', 0.5),
                self::text('contact-line', '{{mobile}}   ·   {{email}}', 8, 38, 69.6, 6, $f['body'], 400, 'palette.text', ['align' => 'center', 'name' => 'Contact line']),
                self::text('web-line', '{{website}}', 8, 42.5, 69.6, 6, $f['body'], 500, 'palette.accent', ['align' => 'center', 'name' => 'Website']),
            ]),
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => 'palette.primary'],
            'elements' => array_merge(
                isset($o['pattern']) ? [self::pattern('pattern-back', 0, 0, 85.6, 54, $o['pattern'], 'palette.accent', 0.12)] : [],
                $frame ? [self::shape('frame-back', 4, 4, 77.6, 46, 'transparent', stroke: 'palette.accent', strokeWidth: 0.3, name: 'Frame')] : [],
                [
                    self::image('logo', 'logo', 34.8, 8, 16, 16, name: 'Logo'),
                    self::text('company', '{{company}}', 8, 27, 69.6, 10, $f['heading'], 600, $p['primary'] === $p['background'] ? 'palette.accent' : '#ffffff', ['align' => 'center', 'letterSpacing' => $tracking, 'textTransform' => 'uppercase', 'name' => 'Company']),
                    self::qr('qr', 36.3, 34, 13, 'palette.primary', '#ffffff', 'QR code'),
                ],
            ),
        ];

        return [$front, $back];
    }

    private static function gradient(array $f, array $o): array
    {
        $grad = ['type' => 'gradient', 'angle' => $o['angle'] ?? 135, 'stops' => [['offset' => 0, 'color' => 'palette.primary'], ['offset' => 1, 'color' => 'palette.secondary']]];
        $front = [
            'background' => $grad,
            'elements' => array_merge([
                self::shape('orb', 58, -14, 40, 40, 'rgba(255,255,255,0.12)', shape: 'circle', name: 'Decorative circle'),
                self::shape('orb-2', 70, 30, 26, 26, 'rgba(255,255,255,0.08)', shape: 'circle', name: 'Decorative circle'),
                self::image('logo', 'logo', 7, 7, 11, 11, name: 'Logo'),
                self::text('company', '{{company}}', 20, 9.5, 40, 6.5, $f['heading'], 600, 'palette.muted', ['name' => 'Company']),
                self::text('name', '{{full_name}}', 7, 24, 60, 15, $f['heading'], 700, '#ffffff', ['name' => 'Name']),
                self::text('title', '{{professional_title}}', 7, 32, 60, 7, $f['body'], 400, 'palette.muted', ['name' => 'Title']),
            ], self::contacts(7, 40, 46, ['phone' => '{{mobile}}', 'mail' => '{{email}}'], $f['body'], '#ffffff', '#ffffff', 5.5, 4.6),
                isset($o['icon']) ? [self::icon('emblem', $o['icon'], 74, 43, 6, '#ffffff')] : []),
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => '#ffffff'],
            'elements' => [
                self::shape('top-band', 0, 0, 85.6, 4, $grad, name: 'Gradient band'),
                self::qr('qr', 8, 12, 26, 'palette.primary', '#ffffff'),
                self::text('cta', 'Tap or scan', 40, 15, 40, 12, $f['heading'], 700, 'palette.primary', ['name' => 'Call to action']),
                self::text('cta-2', 'to save my contact', 40, 22, 40, 8, $f['body'], 400, '#475569', ['name' => 'Call to action']),
                self::text('website', '{{website}}', 40, 31, 40, 7, $f['body'], 600, 'palette.secondary', ['name' => 'Website']),
                self::social('linkedin', 'linkedin', 40, 40, 'palette.primary'),
                self::social('instagram', 'instagram', 47, 40, 'palette.primary'),
                self::social('x', 'x', 54, 40, 'palette.primary'),
                self::social('whatsapp', 'whatsapp', 61, 40, 'palette.primary'),
            ],
        ];

        return [$front, $back];
    }

    private static function split(array $f, array $o): array
    {
        $light = $o['light'] ?? false;
        $textColor = $light ? 'palette.text' : 'palette.text';
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => array_merge(
                [self::shape('panel', 0, 0, 32, 54, 'palette.primary', name: 'Brand panel')],
                isset($o['pattern']) ? [self::pattern('pattern', 0, 0, 32, 54, $o['pattern'], 'palette.accent', 0.18)] : [],
                [
                    self::image('logo', 'logo', 9, 8, 14, 14, name: 'Logo'),
                    self::qr('qr', 8, 27, 16, 'palette.primary', '#ffffff'),
                    self::text('name', '{{full_name}}', 37, 8, 45, 11, $f['heading'], 700, $textColor, ['name' => 'Name']),
                    self::text('title', '{{professional_title}}', 37, 14.5, 45, 6.5, $f['body'], 500, 'palette.accent', ['name' => 'Title']),
                    self::text('company', '{{company}}', 37, 19.5, 45, 6, $f['body'], 400, 'palette.muted', ['name' => 'Company']),
                ],
                self::contacts(37, 28, 45, ['phone' => '{{mobile}}', 'mail' => '{{email}}', 'globe' => '{{website}}', 'map-pin' => '{{address}}'], $f['body'], $textColor, 'palette.accent', 5.6, 5.4),
            ),
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => 'palette.primary'],
            'elements' => array_merge(
                isset($o['pattern']) ? [self::pattern('pattern-back', 0, 0, 85.6, 54, $o['pattern'], 'palette.accent', 0.15)] : [],
                [
                    self::icon('emblem', $o['icon'] ?? 'gem', 38.8, 12, 8, 'palette.accent'),
                    self::text('company-back', '{{company}}', 8, 25, 69.6, 12, $f['heading'], 700, '#ffffff', ['align' => 'center', 'name' => 'Company']),
                    self::text('tagline-back', '{{tagline}}', 8, 33, 69.6, 6.5, $f['body'], 400, 'palette.accent', ['align' => 'center', 'name' => 'Tagline']),
                ],
            ),
        ];

        return [$front, $back];
    }

    private static function diagonal(array $f, array $o): array
    {
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => array_merge([
                self::shape('tri-1', 52, -10, 50, 50, 'palette.primary', shape: 'diamond', name: 'Shape'),
                self::shape('tri-2', 68, 26, 34, 34, 'palette.secondary', shape: 'diamond', name: 'Shape'),
                self::shape('dot', 60, 38, 8, 8, 'palette.accent', shape: 'circle', name: 'Shape'),
            ], ($o['circles'] ?? false) ? [self::shape('ring', 40, 41, 9, 9, 'transparent', shape: 'circle', stroke: 'palette.accent', strokeWidth: 0.8, name: 'Ring')] : [], [
                self::image('logo', 'logo', 7, 7, 11, 11, name: 'Logo'),
                self::text('name', '{{full_name}}', 7, 21, 50, 14, $f['heading'], 800, 'palette.text', ['name' => 'Name', 'lineHeight' => 1.05]),
                self::text('title', '{{professional_title}}', 7, 28.5, 50, 7, $f['body'], 500, 'palette.primary', ['name' => 'Title']),
            ], self::contacts(7, 36, 45, ['phone' => '{{mobile}}', 'mail' => '{{email}}', 'globe' => '{{website}}'], $f['body'], 'palette.text', 'palette.accent', 5.6, 4.8)),
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => 'palette.text'],
            'elements' => [
                self::shape('b-1', -14, 30, 40, 40, 'palette.primary', shape: 'diamond', name: 'Shape'),
                self::shape('b-2', 66, -12, 30, 30, 'palette.secondary', shape: 'diamond', name: 'Shape'),
                self::text('company', '{{company}}', 8, 18, 69.6, 13, $f['heading'], 800, '#ffffff', ['align' => 'center', 'name' => 'Company']),
                self::text('tagline', '{{tagline}}', 8, 26, 69.6, 6.5, $f['body'], 400, 'palette.primary', ['align' => 'center', 'name' => 'Tagline']),
                self::icon('emblem', $o['icon'] ?? 'sparkles', 40.3, 34, 5, 'palette.accent'),
            ],
        ];

        return [$front, $back];
    }

    private static function photo(array $f, array $o): array
    {
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => array_merge([
                self::shape('arc', -24, -24, 52, 52, 'palette.primary', shape: 'circle', name: 'Background shape'),
                self::image('photo', 'photo', 7, 7, 22, 22, mask: 'circle', border: 1, name: 'Profile photo'),
                self::text('name', '{{full_name}}', 36, 9, 46, 12, $f['heading'], 700, 'palette.text', ['name' => 'Name']),
                self::text('title', '{{professional_title}}', 36, 16, 46, 6.5, $f['body'], 500, 'palette.primary', ['name' => 'Title']),
                self::text('company', '{{company}}', 36, 21, 46, 6, $f['body'], 400, 'palette.muted', ['name' => 'Company']),
            ], self::contacts(36, 30, 46, ['phone' => '{{mobile}}', 'mail' => '{{email}}', 'globe' => '{{website}}', 'map-pin' => '{{city}}'], $f['body'], 'palette.text', 'palette.primary', 5.5, 5.2),
                [self::icon('emblem', $o['icon'] ?? 'star', 9, 38, 6, 'palette.accent')]),
        ];
        $back = [
            'background' => ['type' => 'gradient', 'angle' => 120, 'stops' => [['offset' => 0, 'color' => 'palette.primary'], ['offset' => 1, 'color' => 'palette.secondary']]],
            'elements' => [
                self::image('logo', 'logo', 8, 8, 14, 14, name: 'Logo'),
                self::text('company', '{{company}}', 8, 26, 45, 11, $f['heading'], 700, '#ffffff', ['name' => 'Company']),
                self::text('cta', 'Scan to view my profile', 8, 33, 45, 6.5, $f['body'], 400, '#ffffff', ['name' => 'Call to action']),
                self::qr('qr', 57, 13, 22, 'palette.text', '#ffffff'),
            ],
        ];

        return [$front, $back];
    }

    private static function band(array $f, array $o): array
    {
        $front = [
            'background' => ['type' => 'solid', 'color' => 'palette.background'],
            'elements' => [
                self::icon('emblem', $o['icon'] ?? 'star', 7, 7, 7, 'palette.primary'),
                self::text('company', '{{company}}', 16, 8, 50, 7, $f['body'], 600, 'palette.primary', ['letterSpacing' => 100, 'textTransform' => 'uppercase', 'name' => 'Company']),
                self::text('name', '{{full_name}}', 7, 19, 70, 15, $f['heading'], 700, 'palette.text', ['name' => 'Name']),
                self::text('title', '{{professional_title}}', 7, 27.5, 70, 7, $f['body'], 400, 'palette.muted', ['name' => 'Title']),
                self::shape('band', 0, 39, 85.6, 15, 'palette.primary', name: 'Contact band'),
                self::shape('band-accent', 0, 39, 85.6, 1, 'palette.accent', name: 'Band accent'),
                self::icon('i-phone', 'phone', 7, 44.5, 3.4, 'palette.accent'),
                self::text('phone', '{{mobile}}', 11.5, 44.3, 30, 5.5, $f['body'], 500, '#ffffff', ['name' => 'Phone']),
                self::icon('i-mail', 'mail', 43, 44.5, 3.4, 'palette.accent'),
                self::text('email', '{{email}}', 47.5, 44.3, 34, 5.5, $f['body'], 500, '#ffffff', ['name' => 'Email']),
            ],
        ];
        $back = [
            'background' => ['type' => 'solid', 'color' => 'palette.primary'],
            'elements' => [
                self::pattern('pattern', 0, 0, 85.6, 54, $o['pattern'] ?? 'dots', 'palette.accent', 0.2),
                self::shape('card', 22.8, 9, 40, 36, 'palette.background', radius: 2, name: 'Panel'),
                self::qr('qr', 30.8, 12, 24, 'palette.primary', 'palette.background'),
                self::text('web', '{{website}}', 22.8, 37.5, 40, 6, $f['body'], 600, 'palette.primary', ['align' => 'center', 'name' => 'Website']),
            ],
        ];

        return [$front, $back];
    }

    private static function glass(array $f, array $o): array
    {
        $grad = ['type' => 'gradient', 'angle' => $o['angle'] ?? 120, 'stops' => [['offset' => 0, 'color' => 'palette.primary'], ['offset' => 1, 'color' => 'palette.secondary']]];
        $orbs = [
            self::shape('orb-1', -10, -12, 36, 36, '#f472b6', shape: 'circle', name: 'Orb'),
            self::shape('orb-2', 60, 28, 40, 40, '#22d3ee', shape: 'circle', name: 'Orb'),
        ];
        $front = [
            'background' => $grad,
            'elements' => array_merge($orbs, [
                self::shape('glass', 6, 6, 73.6, 42, 'rgba(255,255,255,0.18)', radius: 4, stroke: 'rgba(255,255,255,0.45)', strokeWidth: 0.3, name: 'Glass panel'),
                self::image('logo', 'logo', 11, 11, 10, 10, name: 'Logo'),
                self::text('name', '{{full_name}}', 11, 24, 55, 13, $f['heading'], 700, '#ffffff', ['name' => 'Name']),
                self::text('title', '{{professional_title}}', 11, 31, 55, 6.5, $f['body'], 400, 'palette.muted', ['name' => 'Title']),
                self::text('contact', '{{mobile}}  ·  {{email}}', 11, 38, 62, 5.5, $f['body'], 500, '#ffffff', ['name' => 'Contact line']),
            ]),
        ];
        $back = [
            'background' => $grad,
            'elements' => array_merge($orbs, [
                self::shape('glass-back', 25.8, 7, 34, 40, 'rgba(255,255,255,0.18)', radius: 4, stroke: 'rgba(255,255,255,0.45)', strokeWidth: 0.3, name: 'Glass panel'),
                self::qr('qr', 30.8, 11, 24, '#1e1b4b', '#ffffff'),
                self::text('cta', 'Tap · Scan · Connect', 25.8, 38.5, 34, 5.5, $f['body'], 600, '#ffffff', ['align' => 'center', 'name' => 'Call to action']),
            ]),
        ];

        return [$front, $back];
    }

    // ------------------------------------------------------------------ element helpers

    private static function text(string $id, string $text, float $x, float $y, float $w, float $size, string $font, int $weight, string $color, array $opts = []): array
    {
        $lineHeight = $opts['lineHeight'] ?? 1.2;

        return array_merge([
            'id' => $id, 'type' => $size >= 10 ? 'heading' : 'text', 'name' => $opts['name'] ?? 'Text',
            'x' => $x, 'y' => $y, 'width' => $w, 'height' => round($size * 0.352778 * $lineHeight, 2),
            'rotation' => 0, 'opacity' => 1, 'locked' => false, 'hidden' => false,
            'text' => $text, 'fontFamily' => $font, 'fontSize' => $size, 'fontWeight' => $weight, 'fontStyle' => 'normal',
            'color' => $color, 'align' => 'left', 'letterSpacing' => 0, 'lineHeight' => $lineHeight, 'textTransform' => 'none',
        ], array_diff_key($opts, ['name' => 1]));
    }

    private static function shape(string $id, float $x, float $y, float $w, float $h, string|array $fill, string $shape = 'rect', ?string $stroke = null, float $strokeWidth = 0, float $radius = 0, string $name = 'Shape'): array
    {
        return ['id' => $id, 'type' => 'shape', 'name' => $name, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'opacity' => 1,
            'shape' => $shape, 'fill' => $fill, 'stroke' => $stroke, 'strokeWidth' => $strokeWidth, 'radius' => $radius];
    }

    private static function line(string $id, float $x, float $y, float $w, string $color, float $sw): array
    {
        return ['id' => $id, 'type' => 'line', 'name' => 'Divider', 'x' => $x, 'y' => $y, 'width' => $w, 'height' => 1, 'rotation' => 0, 'opacity' => 1, 'stroke' => $color, 'strokeWidth' => $sw, 'dash' => 'solid'];
    }

    private static function icon(string $id, string $icon, float $x, float $y, float $size, string $color): array
    {
        return ['id' => $id, 'type' => 'icon', 'name' => 'Icon', 'x' => $x, 'y' => $y, 'width' => $size, 'height' => $size, 'rotation' => 0, 'opacity' => 1, 'icon' => $icon, 'color' => $color, 'strokeWidth' => 2];
    }

    private static function social(string $id, string $network, float $x, float $y, string $color): array
    {
        return ['id' => $id, 'type' => 'social', 'name' => ucfirst($network), 'x' => $x, 'y' => $y, 'width' => 5, 'height' => 5, 'rotation' => 0, 'opacity' => 1, 'network' => $network, 'color' => '#ffffff', 'style' => 'circle', 'background' => $color];
    }

    private static function image(string $id, string $type, float $x, float $y, float $w, float $h, string $mask = 'none', float $border = 0, string $name = 'Image'): array
    {
        return ['id' => $id, 'type' => $type, 'name' => $name, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'opacity' => 1,
            'src' => '{{'.$type.'}}', 'fit' => $type === 'photo' ? 'cover' : 'contain', 'mask' => $mask, 'border' => $border, 'borderColor' => 'palette.background'];
    }

    private static function qr(string $id, float $x, float $y, float $size, string $color, string $bg, string $name = 'QR code'): array
    {
        return ['id' => $id, 'type' => 'qr', 'name' => $name, 'x' => $x, 'y' => $y, 'width' => $size, 'height' => $size, 'rotation' => 0, 'opacity' => 1,
            'data' => '{{profile_url}}', 'color' => $color, 'background' => $bg, 'margin' => 1];
    }

    private static function pattern(string $id, float $x, float $y, float $w, float $h, string $pattern, string $color, float $opacity): array
    {
        return ['id' => $id, 'type' => 'pattern', 'name' => 'Pattern', 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'rotation' => 0, 'opacity' => $opacity,
            'pattern' => $pattern, 'color' => $color, 'spacing' => 3, 'size' => 0.45, 'locked' => true];
    }

    /** Icon + text rows for contact details. */
    private static function contacts(float $x, float $y, float $w, array $rows, string $font, string $color, string $iconColor, float $size = 5.8, float $gap = 5.6): array
    {
        $out = [];
        $i = 0;
        foreach ($rows as $icon => $field) {
            $key = trim($field, '{}');
            $rowY = $y + $i * $gap;
            $textHeight = $size * 0.352778 * 1.2;
            $iconSize = round($textHeight * 1.2, 2);
            $out[] = self::icon("i-$key", $icon, $x, round($rowY + $textHeight / 2 - $iconSize / 2, 2), $iconSize, $iconColor);
            $out[] = self::text("c-$key", $field, round($x + $iconSize + 1.4, 2), $rowY, $w - $iconSize - 1.4, $size, $font, 400, $color, ['name' => ucfirst(str_replace('_', ' ', $key))]);
            $i++;
        }

        return $out;
    }
}
