<?php

namespace App\Services\Export;

/**
 * Sets exact print boxes on a PDF with an incremental update (the original
 * bytes are kept and new page dictionaries are appended), so prepress tools
 * see the trim and bleed areas:
 *  MediaBox = full page, BleedBox = trim + bleed, TrimBox = finished card.
 * Works with the classic cross-reference tables Chrome writes; returns the
 * PDF unchanged if the structure isn't recognised.
 */
class PdfBoxes
{
    /**
     * @param  array<int, array{media: array{float, float}, trim: array{float, float, float, float}, bleed: array{float, float, float, float}}>  $pages
     *                                                                                                                                                   sizes in points, boxes as [x1, y1, x2, y2] from the bottom-left
     */
    public static function apply(string $pdf, array $pages): string
    {
        if (! preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $sx)
            || ! preg_match_all('/trailer\s*<<(.*?)>>/s', $pdf, $trailers)
            || ! preg_match('/\/Root\s+(\d+\s+\d+\s+R)/', end($trailers[1]), $root)
            || ! preg_match('/\/Size\s+(\d+)/', end($trailers[1]), $size)) {
            return $pdf;
        }
        $info = preg_match('/\/Info\s+(\d+\s+\d+\s+R)/', end($trailers[1]), $i) ? ' /Info '.$i[1] : '';

        // Page order from the /Kids array of the page tree.
        if (! preg_match('/\/Type\s*\/Pages\b.*?\/Kids\s*\[([^\]]*)\]/s', $pdf, $kids)
            || ! preg_match_all('/(\d+)\s+0\s+R/', $kids[1], $refs)) {
            return $pdf;
        }

        $f = fn (float $v) => rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
        $box = fn (array $b) => '['.implode(' ', array_map($f, $b)).']';
        $append = "\n";
        $entries = [];
        $offset = strlen($pdf) + 1;

        foreach ($refs[1] as $index => $num) {
            if (! isset($pages[$index]) || ! preg_match('/(?<![0-9])'.$num.'\s+0\s+obj\s*<<(.*?)>>\s*endobj/s', $pdf, $obj)) {
                return $pdf;
            }
            $p = $pages[$index];
            $dict = preg_replace('/\/(MediaBox|CropBox|TrimBox|BleedBox|ArtBox)\s*\[[^\]]*\]/', '', $obj[1]);
            $body = $num." 0 obj\n<<".rtrim($dict)
                ."\n/MediaBox ".$box([0, 0, $p['media'][0], $p['media'][1]])
                ."\n/BleedBox ".$box($p['bleed'])
                ."\n/TrimBox ".$box($p['trim'])
                .">>\nendobj\n";
            $entries[(int) $num] = $offset + strlen($append) - 1;
            $append .= $body;
        }

        ksort($entries);
        $xrefOffset = strlen($pdf) + strlen($append);
        $xref = "xref\n";
        foreach ($entries as $num => $at) {
            $xref .= $num." 1\n".sprintf("%010d 00000 n \n", $at);
        }

        return $pdf.$append.$xref.'trailer'."\n<</Size ".$size[1].' /Root '.$root[1].$info.' /Prev '.$sx[1].">>\nstartxref\n".$xrefOffset."\n%%EOF\n";
    }
}
