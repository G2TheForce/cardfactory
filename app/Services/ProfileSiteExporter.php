<?php

namespace App\Services;

use App\Models\DigitalProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Packs a digital profile into a self-contained static website (a ZIP with
 * index.html, the vCard, styles, scripts, fonts and images) that can be
 * uploaded to any static host: Netlify Drop, GitHub Pages, Cloudflare Pages,
 * or plain shared hosting. Nothing in it calls back to this app.
 */
class ProfileSiteExporter
{
    public function __construct(private DigitalProfileService $profiles) {}

    /**
     * Build the ZIP and return its path (a temp file the caller deletes).
     *
     * @param  string|null  $siteUrl  where the site will live, e.g. https://jane.netlify.app.
     *                                Used for the QR code, share button and link previews.
     */
    public function export(DigitalProfile $profile, ?string $siteUrl = null): string
    {
        $profile->loadMissing('customer.company');
        $siteUrl = $siteUrl ? rtrim($siteUrl, '/').'/' : null;
        $files = [];

        $p = $this->profiles->publicData($profile);
        $p['photo'] = $this->image($profile->customer->profile_photo_path, $p['photo'], 'photo', $files);
        $p['cover'] = $this->image($this->profiles->settings($profile)['cover_path'], $p['cover'], 'cover', $files);
        $p['background_image'] = $this->image($this->profiles->settings($profile)['background_image_path'], $p['background_image'], 'background', $files);
        $p['company_logo'] = $this->image($profile->customer->company?->logo_path, $p['company_logo'], 'logo', $files);
        $p['portfolio'] = array_values(array_filter(array_map(function ($item) use (&$files) {
            $url = $this->image($item['path'], $item['url'], 'portfolio-'.$item['id'], $files);

            return $url ? ['url' => $url] + $item : null;
        }, $p['portfolio'])));
        // A QR code needs a public address to point at.
        $p['show_qr'] = $p['show_qr'] && $siteUrl !== null;

        $vcardName = 'contact.vcf';
        $vcard = $this->profiles->vcard($profile);
        $vcard = $siteUrl
            ? str_replace($this->profiles->publicUrl($profile), $siteUrl, $vcard)
            : preg_replace('/^URL;TYPE=PROFILE:.*\r\n/m', '', $vcard);
        $files[$vcardName] = ['contents' => $vcard];

        $assets = $this->buildAssets($files);

        $html = view($this->profiles->publicView($profile), [
            'profile' => $profile,
            'p' => $p,
            'urls' => ['public' => $siteUrl ?? '', 'qr' => $siteUrl ?? '', 'vcard' => $vcardName],
            'preview' => false,
            'available' => true,
            'export' => $assets,
        ])->render();
        $files['index.html'] = ['contents' => $html];
        $files['README.txt'] = ['contents' => $this->readme($p['name'], $siteUrl)];
        if (is_file(public_path('favicon.svg'))) {
            $files['favicon.svg'] = ['path' => public_path('favicon.svg')];
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

    public function filename(DigitalProfile $profile): string
    {
        return ($profile->slug ?: 'profile').'-site.zip';
    }

    /** Copy an image from the public disk into the site and return its relative URL. */
    private function image(?string $path, ?string $url, string $name, array &$files): ?string
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
    private function buildAssets(array &$files): array
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

    private function readme(string $name, ?string $siteUrl): string
    {
        $url = $siteUrl ?? 'your site address';

        return <<<TXT
        Landing page for {$name}
        ========================

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

        The QR code and share button use {$url}. If the address changes,
        export again from SmartCard Studio with the new address.
        Follower counts are as of the export; export again to update them.
        TXT;
    }
}
