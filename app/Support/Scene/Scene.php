<?php

namespace App\Support\Scene;

/**
 * Canonical card scene format shared by themes, designs, the editor and the
 * renderers. All geometry is in millimetres relative to the trimmed card's
 * top-left corner (bleed extends into negative space). Font sizes are in points.
 *
 * Side:   { background: Background, elements: Element[] }
 * Background:
 *   { type: "solid", color }
 *   { type: "gradient", angle, stops: [{ offset: 0..1, color }] }
 *   { type: "image", src, fit: "cover"|"contain", overlay?: color, overlayOpacity?: 0..1, color? }
 * Element (common): id, type, name, x, y, width, height, rotation, opacity, locked, hidden
 *   text|heading: text, fontFamily, fontSize, fontWeight, fontStyle, color, align, letterSpacing, lineHeight, textTransform
 *   shape:   shape (rect|circle|ellipse|triangle|diamond), fill (color or gradient), stroke, strokeWidth, radius
 *   line:    stroke, strokeWidth, dash (solid|dashed|dotted)   (horizontal line of length width, rotate to angle)
 *   icon:    icon (lucide name), color, strokeWidth
 *   social:  network, color, style (plain|circle), background
 *   image|logo|photo: src (url or {{logo}} / {{photo}}), fit, radius, mask (none|circle|rounded), border, borderColor
 *   qr:      data (text or {{profile_url}}), color, background, margin
 *   pattern: pattern (dots|grid|lines|diagonal|waves|circles), color, spacing, size
 *
 * Colours may be hex/rgb(a) values or palette tokens: "palette.primary", "palette.secondary",
 * "palette.accent", "palette.background", "palette.text", "palette.muted".
 * Text may contain merge fields such as {{full_name}} (see Scene::MERGE_FIELDS).
 */
final class Scene
{
    public const ELEMENT_TYPES = ['text', 'heading', 'shape', 'line', 'icon', 'social', 'image', 'logo', 'photo', 'qr', 'pattern'];

    public const SHAPES = ['rect', 'circle', 'ellipse', 'triangle', 'diamond'];

    public const PATTERNS = ['dots', 'grid', 'lines', 'diagonal', 'waves', 'circles'];

    public const PALETTE_KEYS = ['primary', 'secondary', 'accent', 'background', 'text', 'muted'];

    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'linkedin', 'tiktok', 'youtube', 'x', 'github', 'whatsapp', 'website', 'email', 'phone'];

    public const MERGE_FIELDS = [
        'full_name' => 'Full name',
        'first_name' => 'First name',
        'professional_title' => 'Professional title',
        'designation' => 'Designation',
        'department' => 'Department',
        'company' => 'Company name',
        'mobile' => 'Mobile',
        'whatsapp' => 'WhatsApp',
        'office_phone' => 'Office phone',
        'email' => 'Email',
        'website' => 'Website',
        'address' => 'Office address',
        'city' => 'City',
        'country' => 'Country',
        'tagline' => 'Business description',
        'profile_url' => 'Digital profile URL',
        'logo' => 'Company logo (image)',
        'photo' => 'Profile photo (image)',
        'initials' => 'Initials',
    ];

    /** Sample person used for theme previews when no customer is attached. */
    public static function sampleData(): array
    {
        return [
            'full_name' => 'Alexandra Morgan',
            'first_name' => 'Alexandra',
            'professional_title' => 'Chief Executive Officer',
            'designation' => 'Chief Executive Officer',
            'department' => 'Executive Office',
            'company' => 'Northwind Partners',
            'mobile' => '+1 (555) 014-2290',
            'whatsapp' => '+1 (555) 014-2290',
            'office_phone' => '+1 (555) 010-7700',
            'email' => 'alex@northwind.co',
            'website' => 'northwind.co',
            'address' => '120 Market Street, San Francisco',
            'city' => 'San Francisco',
            'country' => 'United States',
            'tagline' => 'Strategy · Growth · Partnerships',
            'profile_url' => rtrim(config('app.url'), '/').'/card/alexandra-morgan',
            'qr_url' => rtrim(config('app.url'), '/').'/card/alexandra-morgan',
            'logo' => null,
            'photo' => null,
            'initials' => 'AM',
        ];
    }

    public static function emptySide(string $background = '#ffffff'): array
    {
        return ['background' => ['type' => 'solid', 'color' => $background], 'elements' => []];
    }
}
