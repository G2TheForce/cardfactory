<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Turns self-contained HTML (inline SVG cards) into vector PDF or raster PNG
 * with headless Chrome/Chromium. Chrome renders the same SVG as the editor,
 * so exports match what people designed, including web fonts.
 */
class ChromeRenderer
{
    public function available(): bool
    {
        return $this->binary() !== null;
    }

    public function binary(): ?string
    {
        $configured = config('smartcard.export.chrome_binary');
        $candidates = $configured ? [$configured] : ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser', '/opt/pw-browsers/chromium/chrome-linux/chrome'];
        foreach ($candidates as $candidate) {
            if (str_contains($candidate, '/')) {
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }

                continue;
            }
            $path = trim((string) @shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }

    /** Render HTML to a PDF file. Page size comes from the HTML's CSS @page rule. */
    public function pdf(string $html, string $outPath, ?int $timeout = null): void
    {
        $this->run($html, ['--no-pdf-header-footer', '--print-to-pdf='.$outPath], $outPath, $timeout);
    }

    /**
     * Render HTML to a PNG screenshot of the given CSS-pixel viewport at a
     * device scale factor (scale 3.125 = 300 DPI, since CSS has 96 px per inch).
     */
    public function png(string $html, int $cssWidth, int $cssHeight, float $scale, string $outPath, bool $transparent = false): void
    {
        $args = [
            '--hide-scrollbars',
            '--window-size='.$cssWidth.','.$cssHeight,
            '--force-device-scale-factor='.rtrim(rtrim(number_format($scale, 5, '.', ''), '0'), '.'),
            '--screenshot='.$outPath,
        ];
        if ($transparent) {
            $args[] = '--default-background-color=00000000';
        }
        $this->run($html, $args, $outPath);
    }

    private function run(string $html, array $args, string $outPath, ?int $timeout = null): void
    {
        $binary = $this->binary() ?? throw new RuntimeException('Headless Chrome is not installed. Set CHROME_PATH or install chromium.');
        $work = storage_path('app/private/tmp/chrome-'.Str::random(12));
        File::ensureDirectoryExists($work);
        $page = $work.'/page.html';
        file_put_contents($page, $html);

        $command = array_merge([$binary, '--headless=new', '--disable-gpu', '--disable-dev-shm-usage', '--mute-audio',
            '--no-first-run', '--disable-extensions',
            // No network: pages are self-contained, and user content can't make the server fetch URLs.
            '--host-resolver-rules=MAP * ~NOTFOUND', '--proxy-server=http://0.0.0.0:9',
            '--run-all-compositor-stages-before-draw', '--virtual-time-budget='.(int) config('smartcard.export.font_wait_ms', 3000)],
            config('smartcard.export.chrome_no_sandbox') ? ['--no-sandbox'] : [],
            $args, ['file://'.$page]);

        try {
            @unlink($outPath);
            $process = new Process($command, $work, ['HOME' => $work]);
            $process->setTimeout($timeout ?? (int) config('smartcard.export.timeout', 120));
            $process->run();
            if (! is_file($outPath) || filesize($outPath) === 0) {
                throw new RuntimeException('Chrome could not render the export: '.Str::limit(trim($process->getErrorOutput()), 500));
            }
        } finally {
            File::deleteDirectory($work);
        }
    }
}
