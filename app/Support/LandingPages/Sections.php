<?php

namespace App\Support\LandingPages;

use App\Support\Scene\IconLibrary;
use Illuminate\Support\Str;

/**
 * The building blocks of a landing page. Each section type has a schema of
 * fields (and optionally a repeatable list of items) that drives both the
 * editor form and sanitising. A page stores its sections as:
 *
 *   [['id' => 'a1b2c3d4', 'type' => 'hero', 'hidden' => false, 'data' => [...fields, 'items' => [...]]], ...]
 *
 * Field types: text, textarea, url, image, select, toggle, icon.
 */
class Sections
{
    public const MAX_SECTIONS = 30;

    public const TYPES = [
        'hero' => [
            'label' => 'Hero', 'icon' => 'panel-top', 'description' => 'Big headline, intro and buttons at the top of the page.',
            'fields' => [
                'variant' => ['type' => 'select', 'label' => 'Layout', 'options' => ['centered' => 'Centred', 'split' => 'Text + image', 'cover' => 'Full background image'], 'default' => 'centered'],
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Headline', 'max' => 120],
                'subheading' => ['type' => 'textarea', 'label' => 'Intro text', 'max' => 400],
                'button_label' => ['type' => 'text', 'label' => 'Main button', 'max' => 40],
                'button_url' => ['type' => 'url', 'label' => 'Main button link'],
                'secondary_label' => ['type' => 'text', 'label' => 'Second button', 'max' => 40],
                'secondary_url' => ['type' => 'url', 'label' => 'Second button link'],
                'image' => ['type' => 'image', 'label' => 'Image'],
            ],
        ],
        'about' => [
            'label' => 'About', 'icon' => 'info', 'description' => 'Your story, with an optional photo.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'body' => ['type' => 'textarea', 'label' => 'Text', 'max' => 2000],
                'image' => ['type' => 'image', 'label' => 'Photo'],
                'image_side' => ['type' => 'select', 'label' => 'Photo side', 'options' => ['right' => 'Right', 'left' => 'Left'], 'default' => 'right'],
            ],
        ],
        'services' => [
            'label' => 'Services', 'icon' => 'briefcase', 'description' => 'What you offer, as cards with icons.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'intro' => ['type' => 'textarea', 'label' => 'Intro', 'max' => 400],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
            ],
            'items' => ['label' => 'Service', 'max' => 12, 'fields' => [
                'icon' => ['type' => 'icon', 'label' => 'Icon', 'default' => 'sparkles'],
                'title' => ['type' => 'text', 'label' => 'Title', 'max' => 80],
                'text' => ['type' => 'textarea', 'label' => 'Description', 'max' => 400],
                'price' => ['type' => 'text', 'label' => 'Price (optional)', 'max' => 30],
            ]],
        ],
        'gallery' => [
            'label' => 'Gallery', 'icon' => 'images', 'description' => 'A grid of photos with captions.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'columns' => ['type' => 'select', 'label' => 'Columns', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
            ],
            'items' => ['label' => 'Photo', 'max' => 24, 'fields' => [
                'image' => ['type' => 'image', 'label' => 'Photo'],
                'caption' => ['type' => 'text', 'label' => 'Caption', 'max' => 120],
            ]],
        ],
        'stats' => [
            'label' => 'Numbers', 'icon' => 'chart-no-axes-column', 'description' => 'Key figures that count up as they appear.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading (optional)', 'max' => 120],
            ],
            'items' => ['label' => 'Number', 'max' => 6, 'fields' => [
                'value' => ['type' => 'text', 'label' => 'Value, e.g. 250+', 'max' => 20],
                'label' => ['type' => 'text', 'label' => 'Label', 'max' => 60],
            ]],
        ],
        'testimonials' => [
            'label' => 'Testimonials', 'icon' => 'quote', 'description' => 'What clients say.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
            ],
            'items' => ['label' => 'Testimonial', 'max' => 12, 'fields' => [
                'quote' => ['type' => 'textarea', 'label' => 'Quote', 'max' => 600],
                'name' => ['type' => 'text', 'label' => 'Name', 'max' => 80],
                'role' => ['type' => 'text', 'label' => 'Role or company', 'max' => 80],
                'rating' => ['type' => 'select', 'label' => 'Stars', 'options' => ['5' => '5', '4' => '4', '0' => 'None'], 'default' => '5'],
            ]],
        ],
        'pricing' => [
            'label' => 'Pricing', 'icon' => 'tag', 'description' => 'Plans or packages side by side.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'intro' => ['type' => 'textarea', 'label' => 'Intro', 'max' => 400],
            ],
            'items' => ['label' => 'Plan', 'max' => 6, 'fields' => [
                'name' => ['type' => 'text', 'label' => 'Name', 'max' => 60],
                'price' => ['type' => 'text', 'label' => 'Price', 'max' => 30],
                'period' => ['type' => 'text', 'label' => 'Per, e.g. / month', 'max' => 30],
                'features' => ['type' => 'textarea', 'label' => 'Features (one per line)', 'max' => 1000],
                'button_label' => ['type' => 'text', 'label' => 'Button', 'max' => 40],
                'button_url' => ['type' => 'url', 'label' => 'Button link'],
                'featured' => ['type' => 'toggle', 'label' => 'Highlight this plan'],
            ]],
        ],
        'faq' => [
            'label' => 'FAQ', 'icon' => 'circle-help', 'description' => 'Questions and answers that open on click.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
            ],
            'items' => ['label' => 'Question', 'max' => 20, 'fields' => [
                'question' => ['type' => 'text', 'label' => 'Question', 'max' => 200],
                'answer' => ['type' => 'textarea', 'label' => 'Answer', 'max' => 1500],
            ]],
        ],
        'cta' => [
            'label' => 'Call to action', 'icon' => 'megaphone', 'description' => 'A bold band that asks visitors to act.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 400],
                'button_label' => ['type' => 'text', 'label' => 'Button', 'max' => 40],
                'button_url' => ['type' => 'url', 'label' => 'Button link'],
            ],
        ],
        'contact' => [
            'label' => 'Contact', 'icon' => 'mail', 'description' => 'Contact details and an enquiry form. Enquiries appear under Leads.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Small label above', 'max' => 60],
                'heading' => ['type' => 'text', 'label' => 'Heading', 'max' => 120],
                'text' => ['type' => 'textarea', 'label' => 'Text', 'max' => 400],
                'email' => ['type' => 'text', 'label' => 'Email', 'max' => 190],
                'phone' => ['type' => 'text', 'label' => 'Phone', 'max' => 40],
                'whatsapp' => ['type' => 'text', 'label' => 'WhatsApp number', 'max' => 40],
                'address' => ['type' => 'textarea', 'label' => 'Address', 'max' => 300],
                'hours' => ['type' => 'textarea', 'label' => 'Opening hours', 'max' => 300],
                'show_form' => ['type' => 'toggle', 'label' => 'Show enquiry form', 'default' => true],
                'show_map' => ['type' => 'toggle', 'label' => 'Show map of the address'],
            ],
        ],
    ];

    /** Icons offered for service cards. */
    public const ICONS = [
        'sparkles', 'star', 'award', 'gem', 'rocket', 'zap', 'shield', 'heart', 'leaf', 'camera', 'briefcase', 'building-2',
        'house', 'key-round', 'utensils', 'coffee', 'cake-slice', 'scissors', 'dumbbell', 'stethoscope', 'scale', 'code-xml',
        'chart-no-axes-column', 'calendar', 'clock', 'truck', 'phone', 'mail', 'globe', 'map-pin', 'users', 'graduation-cap',
    ];

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** A new section of the given type with its default values. */
    public static function make(string $type, array $data = []): array
    {
        $schema = self::TYPES[$type];
        $defaults = [];
        foreach ($schema['fields'] as $name => $field) {
            $defaults[$name] = $field['default'] ?? ($field['type'] === 'toggle' ? false : '');
        }
        if (isset($schema['items'])) {
            $defaults['items'] = [];
        }

        return ['id' => self::newId(), 'type' => $type, 'hidden' => false, 'data' => array_merge($defaults, $data)];
    }

    public static function newId(): string
    {
        return Str::lower(Str::random(8));
    }

    /**
     * Clean sections from the editor: unknown types and fields are dropped,
     * text is trimmed to length, links must be safe and images must belong
     * to this page's upload folder.
     *
     * @param  array<int, mixed>  $sections
     */
    public static function sanitize(array $sections, string $imagePrefix): array
    {
        $out = [];
        $ids = [];
        foreach (array_slice(array_values($sections), 0, self::MAX_SECTIONS) as $section) {
            if (! is_array($section) || ! is_string($section['type'] ?? null) || ! self::exists($section['type'])) {
                continue;
            }
            $schema = self::TYPES[$section['type']];
            $raw = is_array($section['data'] ?? null) ? $section['data'] : [];
            $data = self::fields($schema['fields'], $raw, $imagePrefix);
            if (isset($schema['items'])) {
                $items = is_array($raw['items'] ?? null) ? array_values($raw['items']) : [];
                $data['items'] = array_values(array_map(
                    fn ($item) => self::fields($schema['items']['fields'], is_array($item) ? $item : [], $imagePrefix),
                    array_slice($items, 0, $schema['items']['max'])
                ));
            }
            $id = is_string($section['id'] ?? null) && preg_match('/^[a-z0-9]{4,16}$/', $section['id']) && ! isset($ids[$section['id']])
                ? $section['id'] : self::newId();
            $ids[$id] = true;
            $out[] = ['id' => $id, 'type' => $section['type'], 'hidden' => filter_var($section['hidden'] ?? false, FILTER_VALIDATE_BOOL), 'data' => $data];
        }

        return $out;
    }

    /** Every image path a set of sections uses. */
    public static function imagePaths(array $sections): array
    {
        $paths = [];
        foreach ($sections as $section) {
            $schema = self::TYPES[$section['type']] ?? null;
            if (! $schema) {
                continue;
            }
            foreach ($schema['fields'] as $name => $field) {
                if ($field['type'] === 'image' && filled($section['data'][$name] ?? null)) {
                    $paths[] = $section['data'][$name];
                }
            }
            foreach ($section['data']['items'] ?? [] as $item) {
                foreach ($schema['items']['fields'] ?? [] as $name => $field) {
                    if ($field['type'] === 'image' && filled($item[$name] ?? null)) {
                        $paths[] = $item[$name];
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /** Whether a link is safe to put in an href: web, mail, phone, WhatsApp or an on-page anchor. */
    public static function safeUrl(string $url): bool
    {
        return (bool) preg_match('~^(https?://[^\s<>"]+|mailto:[^\s<>"]+|tel:[+0-9 ()-]+|#[A-Za-z0-9_-]*)$~i', $url);
    }

    private static function fields(array $fields, array $raw, string $imagePrefix): array
    {
        $out = [];
        foreach ($fields as $name => $field) {
            $value = $raw[$name] ?? null;
            $out[$name] = match ($field['type']) {
                'toggle' => $value === null ? (bool) ($field['default'] ?? false) : filter_var($value, FILTER_VALIDATE_BOOL),
                'select' => is_scalar($value) && isset($field['options'][(string) $value]) ? (string) $value : (string) ($field['default'] ?? array_key_first($field['options'])),
                'icon' => is_string($value) && in_array($value, self::ICONS, true) && IconLibrary::exists($value) ? $value : ($field['default'] ?? 'sparkles'),
                'url' => is_string($value) && self::safeUrl(trim($value)) ? Str::limit(trim($value), 500, '') : '',
                'image' => is_string($value) && str_starts_with($value, $imagePrefix) && preg_match('#^[A-Za-z0-9/_.-]+$#', $value) && ! str_contains($value, '..') ? $value : '',
                default => is_scalar($value) ? Str::limit(self::clean((string) $value, $field['type'] === 'textarea'), $field['max'] ?? 255, '') : '',
            };
        }

        return $out;
    }

    private static function clean(string $value, bool $multiline): string
    {
        $value = str_replace("\r\n", "\n", $value);
        $value = preg_replace($multiline ? '/[^\P{C}\n\t]/u' : '/\p{C}/u', '', $value) ?? '';

        return trim($value);
    }
}
