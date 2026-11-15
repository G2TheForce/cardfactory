<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Support\StaticSite;

/**
 * Packs a landing page into a self-contained static website (a ZIP with
 * index.html, styles, scripts, fonts and images) for any static host.
 * Nothing in it calls back to this app, so the enquiry form becomes an
 * email button.
 */
class LandingPageSiteExporter
{
    public function __construct(private LandingPageService $pages) {}

    /**
     * Build the ZIP and return its path (a temp file the caller deletes).
     *
     * @param  string|null  $siteUrl  where the site will live, used for link previews
     */
    public function export(LandingPage $page, ?string $siteUrl = null): string
    {
        $siteUrl = $siteUrl ? rtrim($siteUrl, '/').'/' : null;
        $files = [];
        $copy = function (string $path, string $name = 'image') use (&$files) {
            return StaticSite::image($path, $path, $name, $files);
        };

        $p = $this->pages->publicData($page, $copy);
        $assets = StaticSite::buildAssets($files);

        $files['index.html'] = ['contents' => view('landing-pages.public', [
            'page' => $page,
            'p' => $p,
            'urls' => ['public' => $siteUrl ?? '', 'contact' => null],
            'preview' => false,
            'export' => $assets,
        ])->render()];
        $files['README.txt'] = ['contents' => $this->readme($p['name'])];

        return StaticSite::zip($files);
    }

    public function filename(LandingPage $page): string
    {
        return ($page->slug ?: 'landing-page').'-site.zip';
    }

    private function readme(string $name): string
    {
        $hosting = StaticSite::hostingInstructions();

        return <<<TXT
        {$name}
        ========================

        {$hosting}

        The enquiry form in a static site opens the visitor's email app.
        To collect enquiries in SmartCard Studio, share the live /p/ address instead.
        Edited the page? Export again to get the latest version.
        TXT;
    }
}
