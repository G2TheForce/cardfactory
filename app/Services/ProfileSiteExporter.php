<?php

namespace App\Services;

use App\Models\DigitalProfile;
use App\Support\StaticSite;

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
        $p['photo'] = StaticSite::image($profile->customer->profile_photo_path, $p['photo'], 'photo', $files);
        $p['cover'] = StaticSite::image($this->profiles->settings($profile)['cover_path'], $p['cover'], 'cover', $files);
        $p['background_image'] = StaticSite::image($this->profiles->settings($profile)['background_image_path'], $p['background_image'], 'background', $files);
        $p['company_logo'] = StaticSite::image($profile->customer->company?->logo_path, $p['company_logo'], 'logo', $files);
        $p['portfolio'] = array_values(array_filter(array_map(function ($item) use (&$files) {
            $url = StaticSite::image($item['path'], $item['url'], 'portfolio-'.$item['id'], $files);

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

        $assets = StaticSite::buildAssets($files);

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

        return StaticSite::zip($files);
    }

    public function filename(DigitalProfile $profile): string
    {
        return ($profile->slug ?: 'profile').'-site.zip';
    }

    private function readme(string $name, ?string $siteUrl): string
    {
        $url = $siteUrl ?? 'your site address';
        $hosting = StaticSite::hostingInstructions();

        return <<<TXT
        Landing page for {$name}
        ========================

        {$hosting}

        The QR code and share button use {$url}. If the address changes,
        export again from SmartCard Studio with the new address.
        Follower counts are as of the export; export again to update them.
        TXT;
    }
}
