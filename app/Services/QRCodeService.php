<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR code generation. Produces vector output so QR codes stay sharp at any
 * print resolution. Downloads come as SVG, PNG and vector PDF.
 */
class QRCodeService
{
    public const LEVELS = ['L', 'M', 'Q', 'H'];

    /** Default download style. Colours are #rrggbb; background may be "transparent". */
    public const DEFAULT_STYLE = ['color' => '#111827', 'background' => '#ffffff', 'margin' => 2, 'level' => 'M'];

    /**
     * Returns an SVG path "d" string for the QR modules plus the module count,
     * in a coordinate system where one module = 1 unit.
     *
     * @return array{path: string, modules: int}
     */
    public function modulePath(string $data, string $level = 'M'): array
    {
        ['runs' => $runs, 'modules' => $size] = $this->runs($data, $level);

        $d = '';
        foreach ($runs as [$x, $y, $len]) {
            $d .= 'M'.$x.' '.$y.'h'.$len.'v1h-'.$len.'z';
        }

        return ['path' => $d, 'modules' => $size];
    }

    /** Standalone SVG document for a QR code. */
    public function svg(string $data, int $sizePx = 512, string $color = '#000000', string $background = '#ffffff', int $margin = 2, string $level = 'M'): string
    {
        ['path' => $path, 'modules' => $modules] = $this->modulePath($data, $level);
        $total = $modules + 2 * $margin;
        $bg = $background === 'transparent' ? '' : '<rect width="100%" height="100%" fill="'.e($background).'"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$sizePx.'" height="'.$sizePx.'" viewBox="0 0 '.$total.' '.$total.'" shape-rendering="crispEdges">'
            .$bg.'<path transform="translate('.$margin.' '.$margin.')" fill="'.e($color).'" d="'.$path.'"/></svg>';
    }

    /**
     * PNG bytes. Modules are whole pixels so the code stays crisp; the image
     * is the largest exact multiple of the module grid that fits $sizePx.
     */
    public function png(string $data, int $sizePx = 1024, string $color = '#000000', string $background = '#ffffff', int $margin = 2, string $level = 'M'): string
    {
        ['runs' => $runs, 'modules' => $modules] = $this->runs($data, $level);
        $total = $modules + 2 * $margin;
        $scale = max(1, intdiv($sizePx, $total));
        $px = $total * $scale;

        $img = imagecreatetruecolor($px, $px);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $bg = $background === 'transparent'
            ? imagecolorallocatealpha($img, 255, 255, 255, 127)
            : imagecolorallocate($img, ...$this->rgb($background));
        imagefilledrectangle($img, 0, 0, $px - 1, $px - 1, $bg);
        $fg = imagecolorallocate($img, ...$this->rgb($color));
        foreach ($runs as [$x, $y, $len]) {
            $x0 = ($x + $margin) * $scale;
            $y0 = ($y + $margin) * $scale;
            imagefilledrectangle($img, $x0, $y0, $x0 + $len * $scale - 1, $y0 + $scale - 1, $fg);
        }

        ob_start();
        imagepng($img, null, 9);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /**
     * Vector PDF with one square page of $sizeMm (quiet zone included), so it
     * prints sharp at any resolution.
     */
    public function pdf(string $data, float $sizeMm = 50, string $color = '#000000', string $background = '#ffffff', int $margin = 2, string $level = 'M', string $title = 'QR code'): string
    {
        ['runs' => $runs, 'modules' => $modules] = $this->runs($data, $level);
        $total = $modules + 2 * $margin;
        $pt = round($sizeMm * 72 / 25.4, 3);
        $unit = $pt / $total;
        $f = fn (float $v) => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');

        $content = "q\n";
        if ($background !== 'transparent') {
            $content .= implode(' ', array_map(fn ($c) => $f($c / 255), $this->rgb($background)))." rg\n0 0 {$f($pt)} {$f($pt)} re f\n";
        }
        $content .= implode(' ', array_map(fn ($c) => $f($c / 255), $this->rgb($color)))." rg\n";
        $content .= $f($unit).' 0 0 '.$f(-$unit).' 0 '.$f($pt)." cm\n";
        foreach ($runs as [$x, $y, $len]) {
            $content .= ($x + $margin).' '.($y + $margin).' '.$len." 1 re\n";
        }
        $content .= "f\nQ\n";
        $stream = gzcompress($content, 9);

        $esc = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', '', $s));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$f($pt)} {$f($pt)}] /TrimBox [0 0 {$f($pt)} {$f($pt)}] /Contents 4 0 R /Resources << >> >>",
            '<< /Length '.strlen($stream)." /Filter /FlateDecode >>\nstream\n".$stream."\nendstream",
            '<< /Title ('.$esc($title).') /Producer ('.$esc((string) config('app.name')).') >>',
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info 5 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    /** Merge a stored style with the defaults and keep only valid values. */
    public function style(?array $style): array
    {
        $style = array_merge(self::DEFAULT_STYLE, array_filter($style ?? [], fn ($v) => $v !== null && $v !== ''));
        $hex = fn ($v, $d) => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : $d;

        return [
            'color' => $hex($style['color'], self::DEFAULT_STYLE['color']),
            'background' => $style['background'] === 'transparent' ? 'transparent' : $hex($style['background'], self::DEFAULT_STYLE['background']),
            'margin' => max(0, min(8, (int) $style['margin'])),
            'level' => in_array(strtoupper((string) $style['level']), self::LEVELS, true) ? strtoupper($style['level']) : 'M',
        ];
    }

    /** WCAG contrast ratio between two #rrggbb colours (scanners need roughly 3:1 or more). */
    public function contrast(string $a, string $b): float
    {
        $lum = function (string $hex) {
            $c = array_map(function ($v) {
                $v /= 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            }, $this->rgb($hex));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$l1, $l2] = [$lum($a), $lum($b)];

        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    /**
     * Horizontal runs of dark modules: [x, y, length].
     *
     * @return array{runs: array<int, array{int, int, int}>, modules: int}
     */
    private function runs(string $data, string $level): array
    {
        $matrix = Encoder::encode($data === '' ? ' ' : $data, $this->level($level), 'UTF-8')->getMatrix();
        $size = $matrix->getWidth();
        $runs = [];
        for ($y = 0; $y < $size; $y++) {
            $x = 0;
            while ($x < $size) {
                if ($matrix->get($x, $y) === 1) {
                    $start = $x;
                    while ($x < $size && $matrix->get($x, $y) === 1) {
                        $x++;
                    }
                    $runs[] = [$start, $y, $x - $start];
                } else {
                    $x++;
                }
            }
        }

        return ['runs' => $runs, 'modules' => $size];
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            return [0, 0, 0];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function level(string $level): ErrorCorrectionLevel
    {
        return match (strtoupper($level)) {
            'L' => ErrorCorrectionLevel::L(),
            'Q' => ErrorCorrectionLevel::Q(),
            'H' => ErrorCorrectionLevel::H(),
            default => ErrorCorrectionLevel::M(),
        };
    }
}
