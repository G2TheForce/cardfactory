<?php

namespace Tests\Support;

use App\Services\Export\ChromeRenderer;

/**
 * Stands in for headless Chrome in tests: writes a minimal PDF (one page per
 * ".page" element, classic xref table like Chrome's) or a blank PNG of the
 * requested device-pixel size, and remembers the HTML it was given.
 */
class FakeChromeRenderer extends ChromeRenderer
{
    public array $html = [];

    public function available(): bool
    {
        return true;
    }

    public function binary(): ?string
    {
        return '/usr/bin/fake-chrome';
    }

    public function pdf(string $html, string $outPath, ?int $timeout = null): void
    {
        $this->html[] = $html;
        file_put_contents($outPath, self::minimalPdf(max(1, substr_count($html, 'class="page ')), 300, 200));
    }

    public function png(string $html, int $cssWidth, int $cssHeight, float $scale, string $outPath, bool $transparent = false): void
    {
        $this->html[] = $html;
        $img = imagecreatetruecolor((int) ceil($cssWidth * $scale), (int) ceil($cssHeight * $scale));
        imagepng($img, $outPath);
        imagedestroy($img);
    }

    public static function minimalPdf(int $pages, float $w, float $h): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (3 + $i).' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pages.' >>';
        for ($i = 0; $i < $pages; $i++) {
            $objects[3 + $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] >>";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer
<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
