<?php

namespace App\Support\Scene;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalises a card side scene (see Scene). Unknown keys are
 * dropped, numbers are clamped, and colours / URLs are checked, so stored
 * scenes are always safe to render into SVG.
 */
class SceneValidator
{
    private const MAX_ELEMENTS = 150;

    private const COORD_LIMIT = 500; // mm

    private array $errors = [];

    /**
     * @throws ValidationException
     */
    public function validate(mixed $side, string $attribute = 'scene'): array
    {
        $this->errors = [];
        $clean = $this->side($side, $attribute);

        if ($this->errors) {
            throw ValidationException::withMessages($this->errors);
        }

        return $clean;
    }

    private function side(mixed $side, string $path): array
    {
        if (! is_array($side)) {
            $this->fail($path, 'The card side must be an object.');

            return Scene::emptySide();
        }

        $elements = $side['elements'] ?? [];
        if (! is_array($elements) || ! array_is_list($elements)) {
            $this->fail("$path.elements", 'Elements must be a list.');
            $elements = [];
        }
        if (count($elements) > self::MAX_ELEMENTS) {
            $this->fail("$path.elements", 'A card side may contain at most '.self::MAX_ELEMENTS.' elements.');
            $elements = array_slice($elements, 0, self::MAX_ELEMENTS);
        }

        $ids = [];
        $clean = [];
        foreach ($elements as $i => $element) {
            $el = $this->element($element, "$path.elements.$i");
            if ($el === null) {
                continue;
            }
            if (isset($ids[$el['id']])) {
                $el['id'] = 'el_'.Str::lower(Str::random(8));
            }
            $ids[$el['id']] = true;
            $clean[] = $el;
        }

        return [
            'background' => $this->background($side['background'] ?? null, "$path.background"),
            'elements' => $clean,
        ];
    }

    private function background(mixed $bg, string $path): array
    {
        if (! is_array($bg)) {
            return ['type' => 'solid', 'color' => '#ffffff'];
        }

        return match ($bg['type'] ?? 'solid') {
            'gradient' => [
                'type' => 'gradient',
                'angle' => $this->number($bg['angle'] ?? 135, 0, 360),
                'stops' => $this->stops($bg['stops'] ?? [], "$path.stops"),
            ],
            'image' => array_filter([
                'type' => 'image',
                'src' => $this->src($bg['src'] ?? null, "$path.src"),
                'fit' => in_array($bg['fit'] ?? 'cover', ['cover', 'contain'], true) ? ($bg['fit'] ?? 'cover') : 'cover',
                'color' => $this->color($bg['color'] ?? '#ffffff', "$path.color"),
                'overlay' => isset($bg['overlay']) ? $this->color($bg['overlay'], "$path.overlay") : null,
                'overlayOpacity' => $this->number($bg['overlayOpacity'] ?? 0, 0, 1),
            ], fn ($v) => $v !== null),
            'solid' => ['type' => 'solid', 'color' => $this->color($bg['color'] ?? '#ffffff', "$path.color")],
            default => $this->fail("$path.type", 'Unknown background type.') ?? ['type' => 'solid', 'color' => '#ffffff'],
        };
    }

    private function stops(mixed $stops, string $path): array
    {
        if (! is_array($stops) || count($stops) < 2) {
            $this->fail($path, 'A gradient needs at least two colour stops.');

            return [['offset' => 0, 'color' => '#ffffff'], ['offset' => 1, 'color' => '#000000']];
        }

        return collect(array_slice($stops, 0, 6))->map(fn ($s, $i) => [
            'offset' => $this->number($s['offset'] ?? 0, 0, 1),
            'color' => $this->color($s['color'] ?? '#000000', "$path.$i.color"),
        ])->sortBy('offset')->values()->all();
    }

    private function element(mixed $el, string $path): ?array
    {
        if (! is_array($el) || ! in_array($el['type'] ?? null, Scene::ELEMENT_TYPES, true)) {
            $this->fail("$path.type", 'Unknown or missing element type.');

            return null;
        }

        $type = $el['type'];
        $id = is_string($el['id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $el['id']) ? $el['id'] : 'el_'.Str::lower(Str::random(8));

        $base = [
            'id' => $id,
            'type' => $type,
            'name' => Str::limit(strip_tags((string) ($el['name'] ?? Str::headline($type))), 60, ''),
            'x' => $this->number($el['x'] ?? 0, -self::COORD_LIMIT, self::COORD_LIMIT),
            'y' => $this->number($el['y'] ?? 0, -self::COORD_LIMIT, self::COORD_LIMIT),
            'width' => $this->number($el['width'] ?? 20, 0.1, self::COORD_LIMIT),
            'height' => $this->number($el['height'] ?? 10, 0, self::COORD_LIMIT),
            'rotation' => $this->number($el['rotation'] ?? 0, -360, 360),
            'opacity' => $this->number($el['opacity'] ?? 1, 0, 1),
            'locked' => (bool) ($el['locked'] ?? false),
            'hidden' => (bool) ($el['hidden'] ?? false),
        ];

        $specific = match ($type) {
            'text', 'heading' => [
                'text' => Str::limit((string) ($el['text'] ?? ''), 500, ''),
                'fontFamily' => $this->fontFamily($el['fontFamily'] ?? 'Inter'),
                'fontSize' => $this->number($el['fontSize'] ?? ($type === 'heading' ? 14 : 8), 2, 200),
                'fontWeight' => (int) $this->number($el['fontWeight'] ?? ($type === 'heading' ? 700 : 400), 100, 900),
                'fontStyle' => ($el['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal',
                'color' => $this->color($el['color'] ?? 'palette.text', "$path.color"),
                'align' => in_array($el['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($el['align'] ?? 'left') : 'left',
                'letterSpacing' => $this->number($el['letterSpacing'] ?? 0, -200, 1000), // thousandths of an em
                'lineHeight' => $this->number($el['lineHeight'] ?? 1.2, 0.5, 4),
                'textTransform' => in_array($el['textTransform'] ?? 'none', ['none', 'uppercase', 'lowercase', 'capitalize'], true) ? ($el['textTransform'] ?? 'none') : 'none',
                'underline' => (bool) ($el['underline'] ?? false),
            ],
            'shape' => [
                'shape' => in_array($el['shape'] ?? 'rect', Scene::SHAPES, true) ? ($el['shape'] ?? 'rect') : 'rect',
                'fill' => $this->fill($el['fill'] ?? 'palette.primary', "$path.fill"),
                'stroke' => isset($el['stroke']) ? $this->color($el['stroke'], "$path.stroke") : null,
                'strokeWidth' => $this->number($el['strokeWidth'] ?? 0, 0, 20),
                'radius' => $this->number($el['radius'] ?? 0, 0, 200),
            ],
            'line' => [
                'stroke' => $this->color($el['stroke'] ?? 'palette.accent', "$path.stroke"),
                'strokeWidth' => $this->number($el['strokeWidth'] ?? 0.4, 0.05, 20),
                'dash' => in_array($el['dash'] ?? 'solid', ['solid', 'dashed', 'dotted'], true) ? ($el['dash'] ?? 'solid') : 'solid',
                'cap' => ($el['cap'] ?? 'butt') === 'round' ? 'round' : 'butt',
            ],
            'icon' => [
                'icon' => IconLibrary::exists((string) ($el['icon'] ?? '')) ? $el['icon'] : 'star',
                'color' => $this->color($el['color'] ?? 'palette.accent', "$path.color"),
                'strokeWidth' => $this->number($el['strokeWidth'] ?? 2, 0.5, 4),
            ],
            'social' => [
                'network' => in_array($el['network'] ?? '', Scene::SOCIAL_NETWORKS, true) ? $el['network'] : 'linkedin',
                'color' => $this->color($el['color'] ?? 'palette.accent', "$path.color"),
                'style' => in_array($el['style'] ?? 'plain', ['plain', 'circle', 'square'], true) ? ($el['style'] ?? 'plain') : 'plain',
                'background' => isset($el['background']) ? $this->color($el['background'], "$path.background") : null,
            ],
            'image', 'logo', 'photo' => [
                'src' => $this->src($el['src'] ?? ($type === 'logo' ? '{{logo}}' : ($type === 'photo' ? '{{photo}}' : null)), "$path.src"),
                'fit' => in_array($el['fit'] ?? 'contain', ['cover', 'contain'], true) ? ($el['fit'] ?? 'contain') : 'contain',
                'mask' => in_array($el['mask'] ?? 'none', ['none', 'circle', 'rounded'], true) ? ($el['mask'] ?? 'none') : 'none',
                'radius' => $this->number($el['radius'] ?? 0, 0, 100),
                'border' => $this->number($el['border'] ?? 0, 0, 10),
                'borderColor' => $this->color($el['borderColor'] ?? 'palette.background', "$path.borderColor"),
                'placeholderColor' => $this->color($el['placeholderColor'] ?? 'palette.muted', "$path.placeholderColor"),
            ],
            'qr' => [
                'data' => Str::limit((string) ($el['data'] ?? '{{profile_url}}'), 1000, ''),
                'color' => $this->color($el['color'] ?? '#000000', "$path.color"),
                'background' => $this->color($el['background'] ?? '#ffffff', "$path.background"),
                'margin' => $this->number($el['margin'] ?? 1, 0, 10),
            ],
            'pattern' => [
                'pattern' => in_array($el['pattern'] ?? 'dots', Scene::PATTERNS, true) ? ($el['pattern'] ?? 'dots') : 'dots',
                'color' => $this->color($el['color'] ?? 'palette.accent', "$path.color"),
                'spacing' => $this->number($el['spacing'] ?? 3, 0.5, 50),
                'size' => $this->number($el['size'] ?? 0.4, 0.05, 20),
            ],
        };

        if (in_array($type, ['qr', 'social', 'icon'], true)) {
            $base['height'] = $base['height'] ?: $base['width'];
        }

        return $base + $specific;
    }

    public function color(mixed $value, string $path = 'color'): string
    {
        $value = is_string($value) ? trim($value) : '';

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)
            || preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $value)
            || $value === 'transparent'
            || (str_starts_with($value, 'palette.') && in_array(substr($value, 8), Scene::PALETTE_KEYS, true))) {
            return $value;
        }

        $this->fail($path, "\"$value\" is not a valid colour.");

        return '#000000';
    }

    private function fill(mixed $fill, string $path): string|array
    {
        if (is_array($fill) && ($fill['type'] ?? null) === 'gradient') {
            return ['type' => 'gradient', 'angle' => $this->number($fill['angle'] ?? 135, 0, 360), 'stops' => $this->stops($fill['stops'] ?? [], "$path.stops")];
        }

        return $this->color($fill, $path);
    }

    private function src(mixed $src, string $path): ?string
    {
        if ($src === null || $src === '') {
            return null;
        }
        $src = (string) $src;

        if (in_array($src, ['{{logo}}', '{{photo}}'], true)) {
            return $src;
        }
        if (str_starts_with($src, '/storage/') && ! str_contains($src, '..')) {
            return $src;
        }
        if (filter_var($src, FILTER_VALIDATE_URL) && preg_match('#^https://#i', $src)) {
            return $src;
        }

        $this->fail($path, 'Images must be uploaded assets, https URLs, or the {{logo}} / {{photo}} fields.');

        return null;
    }

    private function fontFamily(mixed $family): string
    {
        $family = is_string($family) ? trim($family) : '';

        return preg_match('/^[A-Za-z0-9 \-]{1,60}$/', $family) ? $family : 'Inter';
    }

    private function number(mixed $value, float $min, float $max): float
    {
        $n = is_numeric($value) ? (float) $value : $min;

        return round(max($min, min($max, $n)), 3);
    }

    private function fail(string $path, string $message): null
    {
        $this->errors[$path][] = $message;

        return null;
    }
}
