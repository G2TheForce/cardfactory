<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR code generation. Produces vector output so QR codes stay sharp at any
 * print resolution. (Download formats are added in Phase 5.)
 */
class QRCodeService
{
    /**
     * Returns an SVG path "d" string for the QR modules plus the module count,
     * in a coordinate system where one module = 1 unit.
     *
     * @return array{path: string, modules: int}
     */
    public function modulePath(string $data, string $level = 'M'): array
    {
        $qr = Encoder::encode($data === '' ? ' ' : $data, $this->level($level), 'UTF-8');
        $matrix = $qr->getMatrix();
        $size = $matrix->getWidth();

        $d = '';
        for ($y = 0; $y < $size; $y++) {
            $x = 0;
            while ($x < $size) {
                if ($matrix->get($x, $y) === 1) {
                    $start = $x;
                    while ($x < $size && $matrix->get($x, $y) === 1) {
                        $x++;
                    }
                    $d .= 'M'.$start.' '.$y.'h'.($x - $start).'v1h-'.($x - $start).'z';
                } else {
                    $x++;
                }
            }
        }

        return ['path' => $d, 'modules' => $size];
    }

    /** Standalone SVG document for a QR code. */
    public function svg(string $data, int $sizePx = 512, string $color = '#000000', string $background = '#ffffff', int $margin = 2): string
    {
        ['path' => $path, 'modules' => $modules] = $this->modulePath($data);
        $total = $modules + 2 * $margin;
        $bg = $background === 'transparent' ? '' : '<rect width="100%" height="100%" fill="'.e($background).'"/>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$sizePx.'" height="'.$sizePx.'" viewBox="0 0 '.$total.' '.$total.'" shape-rendering="crispEdges">'
            .$bg.'<path transform="translate('.$margin.' '.$margin.')" fill="'.e($color).'" d="'.$path.'"/></svg>';
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
