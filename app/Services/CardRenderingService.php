<?php

namespace App\Services;

use App\Support\Fonts;
use App\Support\Scene\IconLibrary;
use App\Support\Scene\Scene;
use Illuminate\Support\Str;

/**
 * Renders a card side scene (see App\Support\Scene\Scene) to a self-contained
 * SVG measured in millimetres. The same output drives previews, thumbnails and
 * (from Phase 6) print exports, so what users see is what gets printed.
 */
class CardRenderingService
{
    private const PT_TO_MM = 0.352778;

    private array $defs = [];

    private array $fonts = [];

    private array $palette = [];

    private array $data = [];

    private string $prefix = '';

    private int $counter = 0;

    public function __construct(private readonly QRCodeService $qr) {}

    /**
     * @param  array{
     *     width_mm?: float, height_mm?: float, bleed_mm?: float, safe_mm?: float, corner_radius_mm?: float,
     *     palette?: array, data?: array, clip_corners?: bool, show_guides?: bool, transparent?: bool,
     *     embed_fonts?: bool, class?: string, title?: string
     * }  $options
     */
    public function render(array $side, array $options = []): string
    {
        $w = (float) ($options['width_mm'] ?? config('smartcard.card.width_mm'));
        $h = (float) ($options['height_mm'] ?? config('smartcard.card.height_mm'));
        $b = (float) ($options['bleed_mm'] ?? 0);
        $radius = (float) ($options['corner_radius_mm'] ?? 0);

        $this->defs = [];
        $this->fonts = [];
        $this->counter = 0;
        $this->prefix = 'c'.Str::lower(Str::random(6));
        $this->palette = $this->normalisePalette($options['palette'] ?? []);
        $this->data = array_merge(Scene::sampleData(), $options['data'] ?? []);

        $background = empty($options['transparent']) ? $this->background($side['background'] ?? [], -$b, -$b, $w + 2 * $b, $h + 2 * $b) : '';

        $body = '';
        foreach ($side['elements'] ?? [] as $element) {
            if (! empty($element['hidden'])) {
                continue;
            }
            $body .= $this->element($element);
        }

        $clip = '';
        if (! empty($options['clip_corners'])) {
            $clipId = $this->id('clip');
            $this->defs[] = '<clipPath id="'.$clipId.'"><rect x="0" y="0" width="'.$this->n($w).'" height="'.$this->n($h).'" rx="'.$this->n($radius).'"/></clipPath>';
            $clip = ' clip-path="url(#'.$clipId.')"';
        }

        $guides = ! empty($options['show_guides']) ? $this->guides($w, $h, $b, (float) ($options['safe_mm'] ?? config('smartcard.card.safe_area_mm')), $radius) : '';

        $style = '';
        if (! empty($options['embed_fonts']) && ($url = Fonts::googleUrl(array_keys($this->fonts)))) {
            $style = '<style>@import url('.$this->attr($url).');</style>';
        }

        $vw = $w + 2 * $b;
        $vh = $h + 2 * $b;
        $class = isset($options['class']) ? ' class="'.$this->attr($options['class']).'"' : '';
        $title = isset($options['title']) ? '<title>'.$this->text($options['title']).'</title>' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"'
            .' width="'.$this->n($vw).'mm" height="'.$this->n($vh).'mm" viewBox="'.$this->n(-$b).' '.$this->n(-$b).' '.$this->n($vw).' '.$this->n($vh).'"'
            .$class.' role="img" preserveAspectRatio="xMidYMid meet">'
            .$title.$style.'<defs>'.implode('', $this->defs).'</defs>'
            .'<g'.$clip.'>'.$background.$body.'</g>'.$guides.'</svg>';
    }

    /** Google Font families referenced by a scene (for <link> tags in pages). */
    public function fontsUsed(array ...$sides): array
    {
        return collect($sides)->flatMap(fn ($side) => $side['elements'] ?? [])
            ->filter(fn ($el) => in_array($el['type'] ?? null, ['text', 'heading'], true))
            ->pluck('fontFamily')->filter()->unique()->values()->all();
    }

    // ------------------------------------------------------------------ backgrounds

    private function background(array $bg, float $x, float $y, float $w, float $h): string
    {
        $rect = fn (string $fill) => '<rect x="'.$this->n($x).'" y="'.$this->n($y).'" width="'.$this->n($w).'" height="'.$this->n($h).'" fill="'.$fill.'"/>';

        return match ($bg['type'] ?? 'solid') {
            'gradient' => $rect('url(#'.$this->gradient($bg).')'),
            'image' => $rect($this->attr($this->color($bg['color'] ?? '#ffffff')))
                .$this->imageTag($bg['src'] ?? null, $x, $y, $w, $h, $bg['fit'] ?? 'cover')
                .(! empty($bg['overlay']) ? '<rect x="'.$this->n($x).'" y="'.$this->n($y).'" width="'.$this->n($w).'" height="'.$this->n($h).'" fill="'.$this->attr($this->color($bg['overlay'])).'" fill-opacity="'.$this->n($bg['overlayOpacity'] ?? 0.4).'"/>' : ''),
            default => $rect($this->attr($this->color($bg['color'] ?? '#ffffff'))),
        };
    }

    private function gradient(array $g): string
    {
        $id = $this->id('grad');
        $rad = deg2rad((float) ($g['angle'] ?? 135));
        $dx = cos($rad) / 2;
        $dy = sin($rad) / 2;
        $stops = collect($g['stops'] ?? [])->map(fn ($s) => '<stop offset="'.$this->n($s['offset']).'" stop-color="'.$this->attr($this->color($s['color'])).'"/>')->implode('');
        $this->defs[] = '<linearGradient id="'.$id.'" x1="'.$this->n(0.5 - $dx).'" y1="'.$this->n(0.5 - $dy).'" x2="'.$this->n(0.5 + $dx).'" y2="'.$this->n(0.5 + $dy).'">'.$stops.'</linearGradient>';

        return $id;
    }

    // ------------------------------------------------------------------ elements

    private function element(array $el): string
    {
        $w = (float) $el['width'];
        $h = (float) $el['height'];
        $content = match ($el['type']) {
            'text', 'heading' => $this->textElement($el, $w, $h),
            'shape' => $this->shape($el, $w, $h),
            'line' => $this->line($el, $w, $h),
            'icon' => $this->icon(IconLibrary::inner($el['icon']), $el['color'], $w, $h, $el['strokeWidth'] ?? 2),
            'social' => $this->social($el, $w, $h),
            'image', 'logo', 'photo' => $this->image($el, $w, $h),
            'qr' => $this->qrElement($el, $w, $h),
            'pattern' => $this->pattern($el, $w, $h),
            default => '',
        };

        $cx = $el['x'] + $w / 2;
        $cy = $el['y'] + $h / 2;
        $transform = 'translate('.$this->n($el['x']).' '.$this->n($el['y']).')';
        if (! empty($el['rotation'])) {
            $transform = 'rotate('.$this->n($el['rotation']).' '.$this->n($cx).' '.$this->n($cy).') '.$transform;
        }
        $opacity = ($el['opacity'] ?? 1) < 1 ? ' opacity="'.$this->n($el['opacity']).'"' : '';

        return '<g data-id="'.$this->attr($el['id']).'" transform="'.$transform.'"'.$opacity.'>'.$content.'</g>';
    }

    private function textElement(array $el, float $w, float $h): string
    {
        $text = $this->merge((string) $el['text']);
        $text = match ($el['textTransform'] ?? 'none') {
            'uppercase' => mb_strtoupper($text),
            'lowercase' => mb_strtolower($text),
            'capitalize' => mb_convert_case($text, MB_CASE_TITLE),
            default => $text,
        };
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $size = $el['fontSize'] * self::PT_TO_MM;
        $lineHeight = $size * ($el['lineHeight'] ?? 1.2);
        [$anchor, $x] = match ($el['align'] ?? 'left') {
            'center' => ['middle', $w / 2],
            'right' => ['end', $w],
            default => ['start', 0],
        };

        $family = $el['fontFamily'] ?? 'Inter';
        $this->fonts[$family] = true;

        $attrs = 'font-family="'.$this->attr(Fonts::stack($family)).'" font-size="'.$this->n($size).'" font-weight="'.(int) $el['fontWeight'].'"'
            .' fill="'.$this->attr($this->color($el['color'])).'" text-anchor="'.$anchor.'" dominant-baseline="central"';
        if (($el['fontStyle'] ?? 'normal') === 'italic') {
            $attrs .= ' font-style="italic"';
        }
        if (! empty($el['letterSpacing'])) {
            $attrs .= ' letter-spacing="'.$this->n($size * $el['letterSpacing'] / 1000).'"';
        }
        if (! empty($el['underline'])) {
            $attrs .= ' text-decoration="underline"';
        }

        $tspans = '';
        foreach ($lines as $i => $line) {
            $tspans .= '<tspan x="'.$this->n($x).'" y="'.$this->n($lineHeight * $i + $lineHeight / 2).'">'.$this->text($line === '' ? ' ' : $line).'</tspan>';
        }

        return '<text '.$attrs.' xml:space="preserve">'.$tspans.'</text>';
    }

    private function shape(array $el, float $w, float $h): string
    {
        $fill = is_array($el['fill']) ? 'url(#'.$this->gradient($el['fill']).')' : $this->attr($this->color($el['fill']));
        $stroke = ! empty($el['stroke']) && $el['strokeWidth'] > 0
            ? ' stroke="'.$this->attr($this->color($el['stroke'])).'" stroke-width="'.$this->n($el['strokeWidth']).'"'
            : '';

        return match ($el['shape']) {
            'circle', 'ellipse' => '<ellipse cx="'.$this->n($w / 2).'" cy="'.$this->n($h / 2).'" rx="'.$this->n($w / 2).'" ry="'.$this->n($h / 2).'" fill="'.$fill.'"'.$stroke.'/>',
            'triangle' => '<polygon points="'.$this->n($w / 2).',0 '.$this->n($w).','.$this->n($h).' 0,'.$this->n($h).'" fill="'.$fill.'"'.$stroke.'/>',
            'diamond' => '<polygon points="'.$this->n($w / 2).',0 '.$this->n($w).','.$this->n($h / 2).' '.$this->n($w / 2).','.$this->n($h).' 0,'.$this->n($h / 2).'" fill="'.$fill.'"'.$stroke.'/>',
            default => '<rect width="'.$this->n($w).'" height="'.$this->n($h).'" rx="'.$this->n(min($el['radius'] ?? 0, $w / 2, $h / 2)).'" fill="'.$fill.'"'.$stroke.'/>',
        };
    }

    private function line(array $el, float $w, float $h): string
    {
        $sw = (float) $el['strokeWidth'];
        $dash = match ($el['dash'] ?? 'solid') {
            'dashed' => ' stroke-dasharray="'.$this->n($sw * 4).' '.$this->n($sw * 3).'"',
            'dotted' => ' stroke-dasharray="0 '.$this->n($sw * 2).'" stroke-linecap="round"',
            default => ($el['cap'] ?? 'butt') === 'round' ? ' stroke-linecap="round"' : '',
        };

        return '<line x1="0" y1="'.$this->n($h / 2).'" x2="'.$this->n($w).'" y2="'.$this->n($h / 2).'" stroke="'.$this->attr($this->color($el['stroke'])).'" stroke-width="'.$this->n($sw).'"'.$dash.'/>';
    }

    private function icon(?string $inner, string $color, float $w, float $h, float $strokeWidth = 2): string
    {
        if (! $inner) {
            return '';
        }
        $size = min($w, $h);
        $scale = $size / 24;

        return '<g transform="translate('.$this->n(($w - $size) / 2).' '.$this->n(($h - $size) / 2).') scale('.$this->n($scale).')" fill="none" stroke="'.$this->attr($this->color($color)).'"'
            .' stroke-width="'.$this->n($strokeWidth).'" stroke-linecap="round" stroke-linejoin="round">'.$inner.'</g>';
    }

    private function social(array $el, float $w, float $h): string
    {
        $inner = IconLibrary::social($el['network']);
        if (($el['style'] ?? 'plain') === 'plain') {
            return $this->icon($inner, $el['color'], $w, $h);
        }

        $size = min($w, $h);
        $bg = $this->attr($this->color($el['background'] ?? 'palette.accent'));
        $shape = $el['style'] === 'circle'
            ? '<circle cx="'.$this->n($w / 2).'" cy="'.$this->n($h / 2).'" r="'.$this->n($size / 2).'" fill="'.$bg.'"/>'
            : '<rect x="'.$this->n(($w - $size) / 2).'" y="'.$this->n(($h - $size) / 2).'" width="'.$this->n($size).'" height="'.$this->n($size).'" rx="'.$this->n($size * 0.2).'" fill="'.$bg.'"/>';
        $pad = $size * 0.22;

        return $shape.'<g transform="translate('.$this->n($pad).' '.$this->n($pad).')">'.$this->icon($inner, $el['color'], $w - 2 * $pad, $h - 2 * $pad, 2.2).'</g>';
    }

    private function image(array $el, float $w, float $h): string
    {
        $src = $el['src'] ?? null;
        if ($src === '{{logo}}' || $src === '{{photo}}') {
            $src = $this->data[trim($src, '{}')] ?? null;
        }

        $clip = '';
        if (($el['mask'] ?? 'none') !== 'none' || ($el['radius'] ?? 0) > 0) {
            $id = $this->id('mask');
            $shape = ($el['mask'] ?? 'none') === 'circle'
                ? '<ellipse cx="'.$this->n($w / 2).'" cy="'.$this->n($h / 2).'" rx="'.$this->n($w / 2).'" ry="'.$this->n($h / 2).'"/>'
                : '<rect width="'.$this->n($w).'" height="'.$this->n($h).'" rx="'.$this->n(($el['mask'] ?? '') === 'rounded' ? max($el['radius'] ?? 0, min($w, $h) * 0.12) : $el['radius']).'"/>';
            $this->defs[] = '<clipPath id="'.$id.'">'.$shape.'</clipPath>';
            $clip = ' clip-path="url(#'.$id.')"';
        }

        if ($src) {
            $content = $this->imageTag($src, 0, 0, $w, $h, $el['fit'] ?? 'contain');
        } else {
            // Placeholder so empty logo / photo slots remain visible and printable.
            $icon = $el['type'] === 'photo' ? 'user' : ($el['type'] === 'logo' ? 'gem' : 'image');
            $content = '<rect width="'.$this->n($w).'" height="'.$this->n($h).'" fill="'.$this->attr($this->color($el['placeholderColor'] ?? 'palette.muted')).'" fill-opacity="0.35"/>'
                .'<g transform="translate('.$this->n($w * 0.25).' '.$this->n($h * 0.25).')">'.$this->icon(IconLibrary::inner($icon), $el['placeholderColor'] ?? 'palette.muted', $w * 0.5, $h * 0.5, 1.6).'</g>';
        }

        $border = '';
        if (($el['border'] ?? 0) > 0) {
            $b = (float) $el['border'];
            $color = $this->attr($this->color($el['borderColor'] ?? '#ffffff'));
            $border = ($el['mask'] ?? 'none') === 'circle'
                ? '<ellipse cx="'.$this->n($w / 2).'" cy="'.$this->n($h / 2).'" rx="'.$this->n($w / 2 - $b / 2).'" ry="'.$this->n($h / 2 - $b / 2).'" fill="none" stroke="'.$color.'" stroke-width="'.$this->n($b).'"/>'
                : '<rect x="'.$this->n($b / 2).'" y="'.$this->n($b / 2).'" width="'.$this->n($w - $b).'" height="'.$this->n($h - $b).'" rx="'.$this->n($el['radius'] ?? 0).'" fill="none" stroke="'.$color.'" stroke-width="'.$this->n($b).'"/>';
        }

        return '<g'.$clip.'>'.$content.'</g>'.$border;
    }

    private function imageTag(?string $src, float $x, float $y, float $w, float $h, string $fit): string
    {
        if (! $src) {
            return '';
        }
        $aspect = $fit === 'cover' ? 'xMidYMid slice' : 'xMidYMid meet';

        return '<image x="'.$this->n($x).'" y="'.$this->n($y).'" width="'.$this->n($w).'" height="'.$this->n($h).'" preserveAspectRatio="'.$aspect.'" href="'.$this->attr($src).'" xlink:href="'.$this->attr($src).'"/>';
    }

    private function qrElement(array $el, float $w, float $h): string
    {
        $data = $this->merge((string) $el['data']);
        ['path' => $path, 'modules' => $modules] = $this->qr->modulePath($data);
        $margin = (float) ($el['margin'] ?? 1);
        $size = min($w, $h);
        $scale = $size / ($modules + 2 * $margin);
        $bg = $this->color($el['background'] ?? '#ffffff');

        return ($bg !== 'transparent' ? '<rect x="'.$this->n(($w - $size) / 2).'" y="'.$this->n(($h - $size) / 2).'" width="'.$this->n($size).'" height="'.$this->n($size).'" fill="'.$this->attr($bg).'"/>' : '')
            .'<path transform="translate('.$this->n(($w - $size) / 2 + $margin * $scale).' '.$this->n(($h - $size) / 2 + $margin * $scale).') scale('.$this->n($scale, 5).')"'
            .' fill="'.$this->attr($this->color($el['color'] ?? '#000000')).'" shape-rendering="crispEdges" d="'.$path.'"/>';
    }

    private function pattern(array $el, float $w, float $h): string
    {
        $id = $this->id('pat');
        $s = (float) $el['spacing'];
        $z = (float) $el['size'];
        $c = $this->attr($this->color($el['color']));
        $tile = match ($el['pattern']) {
            'grid' => '<path d="M'.$this->n($s).' 0V'.$this->n($s).'M0 '.$this->n($s).'H'.$this->n($s).'" fill="none" stroke="'.$c.'" stroke-width="'.$this->n($z / 2).'"/>',
            'lines' => '<line x1="0" y1="'.$this->n($s / 2).'" x2="'.$this->n($s).'" y2="'.$this->n($s / 2).'" stroke="'.$c.'" stroke-width="'.$this->n($z / 2).'"/>',
            'diagonal' => '<path d="M0 '.$this->n($s).'L'.$this->n($s).' 0M'.$this->n(-$s / 2).' '.$this->n($s / 2).'L'.$this->n($s / 2).' '.$this->n(-$s / 2).'M'.$this->n($s / 2).' '.$this->n($s * 1.5).'L'.$this->n($s * 1.5).' '.$this->n($s / 2).'" stroke="'.$c.'" stroke-width="'.$this->n($z / 2).'"/>',
            'waves' => '<path d="M0 '.$this->n($s / 2).'Q'.$this->n($s / 4).' '.$this->n($s / 4).' '.$this->n($s / 2).' '.$this->n($s / 2).'T'.$this->n($s).' '.$this->n($s / 2).'" fill="none" stroke="'.$c.'" stroke-width="'.$this->n($z / 2).'"/>',
            'circles' => '<circle cx="'.$this->n($s / 2).'" cy="'.$this->n($s / 2).'" r="'.$this->n($s / 2 - $z).'" fill="none" stroke="'.$c.'" stroke-width="'.$this->n($z / 2).'"/>',
            default => '<circle cx="'.$this->n($s / 2).'" cy="'.$this->n($s / 2).'" r="'.$this->n($z).'" fill="'.$c.'"/>',
        };
        $this->defs[] = '<pattern id="'.$id.'" width="'.$this->n($s).'" height="'.$this->n($s).'" patternUnits="userSpaceOnUse">'.$tile.'</pattern>';

        return '<rect width="'.$this->n($w).'" height="'.$this->n($h).'" fill="url(#'.$id.')"/>';
    }

    private function guides(float $w, float $h, float $b, float $safe, float $radius): string
    {
        $out = '<g fill="none" stroke-width="0.25" pointer-events="none" data-guides="1">';
        if ($b > 0) {
            $out .= '<rect x="'.$this->n(-$b).'" y="'.$this->n(-$b).'" width="'.$this->n($w + 2 * $b).'" height="'.$this->n($h + 2 * $b).'" stroke="#ef4444" stroke-dasharray="1 1"/>';
        }
        $out .= '<rect x="0" y="0" width="'.$this->n($w).'" height="'.$this->n($h).'" rx="'.$this->n($radius).'" stroke="#111827" stroke-opacity="0.5"/>';
        $out .= '<rect x="'.$this->n($safe).'" y="'.$this->n($safe).'" width="'.$this->n($w - 2 * $safe).'" height="'.$this->n($h - 2 * $safe).'" stroke="#0ea5e9" stroke-dasharray="1.2 0.8"/>';

        return $out.'</g>';
    }

    // ------------------------------------------------------------------ helpers

    private function normalisePalette(array $palette): array
    {
        return array_merge([
            'primary' => '#111827', 'secondary' => '#374151', 'accent' => '#4f46e5',
            'background' => '#ffffff', 'text' => '#111827', 'muted' => '#6b7280',
        ], array_filter($palette, fn ($v) => is_string($v) && $v !== ''));
    }

    private function color(string $value): string
    {
        if (str_starts_with($value, 'palette.')) {
            return $this->palette[substr($value, 8)] ?? '#000000';
        }

        return $value;
    }

    private function merge(string $text): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($this->data[$m[1]] ?? ''), $text);
    }

    private function id(string $kind): string
    {
        return $this->prefix.'-'.$kind.'-'.(++$this->counter);
    }

    private function n(float|int|string $value, int $precision = 3): string
    {
        $s = rtrim(rtrim(number_format((float) $value, $precision, '.', ''), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }

    private function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
    }
}
