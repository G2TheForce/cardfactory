<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Shared helpers for the static website exports (digital profiles and landing
 * pages): copy public-disk images and the built CSS/JS/fonts into the site,
 * and pack everything into a ZIP.
 *
 * $files maps a path inside the site to ['path' => source file] or ['contents' => string].
 */
class StaticSite
{
    /** Copy an image from the public disk into the site and return its relative URL. */
    public static function image(?string $path, ?string $url, string $name, array &$files): ?string
    {
        if (! $url || ! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $file = 'assets/'.Str::slug($name).'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        $files[$file] = ['path' => Storage::disk('public')->path($path)];

        return $file;
    }

    /**
     * Add the built stylesheet, script and the fonts the stylesheet uses.
     * Returns the relative URLs for the page, or nulls when there's no build
     * (the page then falls back to plain styles).
     *
     * @return array{css: ?string, js: ?string}
     */
    public static function buildAssets(array &$files): array
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : null;
        $css = $manifest['resources/css/app.css']['file'] ?? null;
        $js = $manifest['resources/js/app.js']['file'] ?? null;
        $out = ['css' => null, 'js' => null];

        if ($css && is_file($cssPath = public_path('build/'.$css))) {
            // Point font URLs (/build/assets/x.woff2) at the copies next to the stylesheet.
            $contents = preg_replace_callback('#url\(\s*["\']?(?:/build/)?assets/([^"\')]+)["\']?\s*\)#', function ($m) use (&$files) {
                $file = basename($m[1]);
                if (is_file($src = public_path('build/assets/'.$file))) {
                    $files['assets/'.$file] = ['path' => $src];
                }

                return 'url('.$file.')';
            }, (string) file_get_contents($cssPath));
            $files['assets/app.css'] = ['contents' => $contents];
            $out['css'] = 'assets/app.css';
        }
        if ($js && is_file($jsPath = public_path('build/'.$js))) {
            $files['assets/app.js'] = ['path' => $jsPath];
            $out['js'] = 'assets/app.js';
        }

        return $out;
    }

    /** Write the files to a temporary ZIP and return its path (the caller deletes it). */
    public static function zip(array $files): string
    {
        if (is_file(public_path('favicon.svg'))) {
            $files['favicon.svg'] ??= ['path' => public_path('favicon.svg')];
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'site').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }
        foreach ($files as $name => $file) {
            isset($file['path']) ? $zip->addFile($file['path'], $name) : $zip->addFromString($name, $file['contents']);
        }
        $zip->close();

        return $zipPath;
    }

    /** Upload instructions included in every exported site. */
    public static function hostingInstructions(): string
    {
        return <<<'TXT'
        This folder is a complete website. Upload it as-is to any static host:

        Netlify Drop (no account setup, free)
          1. Open https://app.netlify.com/drop
          2. Drag this whole folder onto the page.
          3. Netlify gives you a public address. Rename it under Site settings.

        GitHub Pages (free)
          1. Create a repository and upload these files to it.
          2. Settings > Pages > Deploy from branch > main / root.

        Cloudflare Pages (free)
          1. Workers & Pages > Create > Pages > Upload assets.
          2. Drop this folder in.

        Any web hosting (cPanel, FTP)
          Upload everything into public_html (or a sub-folder).
        TXT;
    }
}
