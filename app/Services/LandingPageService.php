<?php

namespace App\Services;

use App\Mail\LandingPageLeadReceived;
use App\Models\BrandKit;
use App\Models\Customer;
use App\Models\LandingPage;
use App\Models\LandingPageLead;
use App\Models\User;
use App\Support\LandingPages\Sections;
use App\Support\LandingPages\Styles;
use App\Support\LandingPages\Templates;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Landing pages: multi-section marketing pages for a customer's business.
 * Creating from a template, editing sections and style, brand kits, image
 * uploads, publishing, the data the public page renders, and enquiries.
 */
class LandingPageService
{
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])$/';

    public const MAX_IMAGE_EDGE = 2400;

    public function __construct(
        private readonly AssetService $assets,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name?: ?string, slug?: ?string, template?: ?string, brand_kit?: ?BrandKit}  $data
     */
    public function create(Customer $customer, array $data, User $actor): LandingPage
    {
        $customer->loadMissing('company');
        $template = Templates::exists($data['template'] ?? '') ? $data['template'] : 'blank';
        $company = $customer->company?->name ?: $customer->full_name;
        $slug = ($data['slug'] ?? null) ?: $this->suggestSlug($company);
        $this->assertSlugAvailable($slug);

        $built = Templates::build($template, [
            'company' => $company,
            'name' => $customer->full_name,
            'email' => $customer->email ?: $customer->company?->email,
            'phone' => $customer->mobile ?: ($customer->office_phone ?: $customer->company?->phone),
            'whatsapp' => $customer->whatsapp,
            'address' => collect([$customer->office_address, $customer->city, $customer->country])->filter()->implode(', '),
        ]);

        return DB::transaction(function () use ($customer, $data, $actor, $template, $slug, $built, $company) {
            $page = LandingPage::create([
                'customer_id' => $customer->id,
                'created_by' => $actor->id,
                'name' => ($data['name'] ?? null) ?: $company,
                'slug' => $slug,
                'template' => $template,
                'sections' => [],
                'style' => Styles::sanitize($built['style']),
                'settings' => $this->defaultSettings(),
                'is_published' => false,
            ]);
            $page->sections = Sections::sanitize($built['sections'], $this->imagePrefix($page));
            $page->save();

            if ($data['brand_kit'] ?? null) {
                $this->applyBrandKit($page, $data['brand_kit'], $actor, log: false);
            }
            $this->logger->log('landing_page.created', $page, 'Created landing page /p/'.$page->slug.' for "'.$customer->full_name.'"', ['template' => $template], $actor->id);

            return $page;
        });
    }

    /**
     * Save the editor: name, address, sections, style, page settings and status.
     *
     * @param  array<string, mixed>  $data  validated LandingPageRequest data
     */
    public function update(LandingPage $page, array $data, User $actor, bool $log = true): LandingPage
    {
        if (isset($data['slug']) && $data['slug'] !== $page->slug) {
            $this->assertSlugAvailable($data['slug'], $page);
            $page->slug = $data['slug'];
        }
        if (isset($data['name'])) {
            $page->name = $data['name'];
        }
        if (array_key_exists('sections', $data)) {
            $page->sections = Sections::sanitize((array) $data['sections'], $this->imagePrefix($page));
        }
        if (array_key_exists('style', $data)) {
            $page->style = Styles::sanitize((array) $data['style']);
        }
        if (array_key_exists('settings', $data)) {
            $page->settings = $this->sanitizeSettings((array) $data['settings'], $page);
        }
        if (array_key_exists('is_published', $data)) {
            $this->setPublished($page, (bool) $data['is_published']);
        }
        $page->save();

        if ($log) {
            $this->logger->log('landing_page.updated', $page, 'Edited landing page /p/'.$page->slug, [], $actor->id);
        }

        return $page;
    }

    public function publish(LandingPage $page, bool $published, User $actor): LandingPage
    {
        $this->setPublished($page, $published);
        $page->save();
        $this->logger->log($published ? 'landing_page.published' : 'landing_page.unpublished', $page, ($published ? 'Published' : 'Unpublished').' landing page /p/'.$page->slug, [], $actor->id);

        return $page;
    }

    /** Use a brand kit's colours, fonts, logo and company details on the page. */
    public function applyBrandKit(LandingPage $page, BrandKit $kit, User $actor, bool $log = true): LandingPage
    {
        $style = $page->style ?? Styles::defaults();
        $style['primary'] = $kit->primary_color ?: $style['primary'];
        $style['accent'] = $kit->accent_color ?: ($kit->secondary_color ?: $style['accent']);
        $heading = strtolower((string) ($kit->typography['heading'] ?? $kit->typography['font'] ?? ''));
        if (str_contains($heading, 'serif') && ! str_contains($heading, 'sans')) {
            $style['font'] = 'elegant';
        }
        $settings = $page->settings ?? $this->defaultSettings();
        if ($kit->logo_path) {
            $settings['logo_path'] = $kit->logo_path;
        }

        $company = $kit->company_details ?? [];
        $sections = $page->sections ?? [];
        foreach ($sections as &$section) {
            if ($section['type'] === 'contact') {
                foreach (['email' => 'email', 'phone' => 'phone', 'address' => 'address'] as $field => $key) {
                    if (blank($section['data'][$field] ?? null) && filled($company[$key] ?? null)) {
                        $section['data'][$field] = $company[$key];
                    }
                }
            }
        }
        unset($section);

        $page->forceFill([
            'brand_kit_id' => $kit->id,
            'style' => Styles::sanitize($style),
            'settings' => $settings,
            'sections' => Sections::sanitize($sections, $this->imagePrefix($page)),
        ])->save();

        if ($log) {
            $this->logger->log('landing_page.brand_kit_applied', $page, 'Applied brand kit "'.$kit->name.'" to landing page /p/'.$page->slug, [], $actor->id);
        }

        return $page;
    }

    /**
     * Store an image for the page's sections (re-encoded, metadata stripped).
     *
     * @return array{path: string, url: string}
     *
     * @throws ValidationException
     */
    public function storeImage(LandingPage $page, UploadedFile $file, User $actor): array
    {
        $stored = $this->assets->storeImageFile($file, rtrim($this->imagePrefix($page), '/'), self::MAX_IMAGE_EDGE, 'image');
        $this->logger->log('landing_page.image_uploaded', $page, 'Uploaded an image to landing page /p/'.$page->slug, [], $actor->id);

        return ['path' => $stored['path'], 'url' => Storage::disk('public')->url($stored['path'])];
    }

    public function delete(LandingPage $page, User $actor): void
    {
        $this->logger->log('landing_page.deleted', $page, 'Deleted landing page /p/'.$page->slug, [], $actor->id);
        $page->delete();
    }

    /** Whether /p/{slug} should show the page. */
    public function isAvailable(LandingPage $page): bool
    {
        $customer = $page->customer;

        return $page->is_published && ! $page->trashed() && $customer && ! $customer->trashed() && $customer->status === 'active';
    }

    public function publicUrl(LandingPage $page): string
    {
        return route('landing-pages.public', $page->slug);
    }

    public function suggestSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 50, '') ?: 'page';
        if (strlen($base) < 3) {
            $base .= '-page';
        }
        $slug = $base;
        $i = 2;
        while (! $this->slugAvailable($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function slugAvailable(string $slug, ?LandingPage $ignore = null): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1
            && ! in_array($slug, config('smartcard.profiles.reserved_slugs', []), true)
            && ! LandingPage::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))->exists();
    }

    /** Uploaded images live (and must stay) under this folder on the public disk. */
    public function imagePrefix(LandingPage $page): string
    {
        return 'landing-pages/'.$page->ulid.'/';
    }

    public function settings(LandingPage $page): array
    {
        return array_merge($this->defaultSettings(), $page->settings ?? []);
    }

    /**
     * Everything the public page renders: visible sections with anchors and
     * image URLs, navigation, colour tokens, SEO and footer details.
     *
     * @param  callable(string $path, string $name): ?string|null  $imageUrl  maps a public-disk path to a URL
     *                                                                        (the static export copies files instead)
     */
    public function publicData(LandingPage $page, ?callable $imageUrl = null): array
    {
        $page->loadMissing('customer.company');
        $imageUrl ??= fn (string $path) => Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
        $settings = $this->settings($page);
        $customer = $page->customer;
        $company = $customer?->company?->name ?: ($customer?->full_name ?? $page->name);

        $sections = [];
        $anchors = [];
        foreach (Sections::sanitize($page->sections ?? [], $this->imagePrefix($page)) as $section) {
            if ($section['hidden']) {
                continue;
            }
            $type = $section['type'];
            $anchors[$type] = ($anchors[$type] ?? 0) + 1;
            $section['anchor'] = $anchors[$type] === 1 ? $type : $type.'-'.$anchors[$type];
            $section['data'] = $this->resolveImages($section, $imageUrl);
            $section['data'] = array_replace($section['data'], $this->extras($section));
            // List sections without items (or a gallery without photos) stay off the live page.
            $section['empty'] = isset(Sections::TYPES[$type]['items'])
                && collect($section['data']['items'])->filter(fn ($item) => $type !== 'gallery' || $item['image'])->isEmpty();
            $sections[] = $section;
        }

        $navLabels = ['about' => 'About', 'services' => 'Services', 'gallery' => 'Gallery', 'testimonials' => 'Reviews', 'pricing' => 'Pricing', 'faq' => 'FAQ', 'contact' => 'Contact'];
        $nav = collect($sections)->filter(fn ($s) => isset($navLabels[$s['type']]) && $s['anchor'] === $s['type'] && ! $s['empty'])
            ->map(fn ($s) => ['href' => '#'.$s['anchor'], 'label' => $navLabels[$s['type']]])->values()->all();
        $cta = collect($sections)->firstWhere('type', 'contact') ? ['href' => '#contact', 'label' => $settings['nav_cta'] ?: 'Get in touch'] : null;

        $social = collect($customer?->social_links ?? [])
            ->filter(fn ($url, $key) => is_string($url) && preg_match('#^https?://#i', $url) && is_string($key))
            ->map(fn ($url, $key) => ['key' => $key, 'url' => $url, 'label' => Str::headline($key)])->values()->all();

        $logo = $settings['logo_path'] ? $imageUrl($settings['logo_path'], 'logo') : null;
        $hero = collect($sections)->firstWhere('type', 'hero');

        return [
            'name' => $page->name,
            'company' => $company,
            'title' => $settings['seo_title'] ?: (filled($hero['data']['heading'] ?? null) ? $company.' · '.$hero['data']['heading'] : $company),
            'description' => $settings['seo_description'] ?: Str::limit((string) ($hero['data']['subheading'] ?? ''), 160),
            'share_image' => $hero['data']['image'] ?? null,
            'allow_indexing' => (bool) $settings['allow_indexing'],
            'logo' => $logo,
            'show_nav' => (bool) $settings['show_nav'],
            'nav' => $nav,
            'nav_cta' => $cta,
            'sections' => $sections,
            'style' => Styles::resolve($page->style ?? []),
            'social' => $social,
            'footer' => $settings['footer_text'] ?: '© '.now()->year.' '.$company.'. All rights reserved.',
        ];
    }

    /**
     * Save an enquiry from the public contact form and email the owner.
     *
     * @param  array{name: string, email?: ?string, phone?: ?string, message?: ?string}  $data
     */
    public function storeLead(LandingPage $page, array $data): LandingPageLead
    {
        $lead = $page->leads()->create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        if ($to = $this->notifyAddress($page)) {
            try {
                Mail::to($to)->send(new LandingPageLeadReceived($lead, $page->name));
            } catch (Throwable $e) {
                // The enquiry is saved either way; it is listed on the page's screen.
                Log::warning('Landing page lead email failed: '.$e->getMessage(), ['landing_page' => $page->ulid]);
            }
        }

        return $lead;
    }

    /** Where enquiry emails go: the page setting, else the contact section's email, else the customer's. */
    public function notifyAddress(LandingPage $page): ?string
    {
        $settings = $this->settings($page);
        if (! $settings['notify_leads']) {
            return null;
        }
        $contact = collect($page->sections ?? [])->firstWhere('type', 'contact');
        $candidates = [$settings['notify_email'], $contact['data']['email'] ?? null, $page->customer?->email];

        return collect($candidates)->first(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL));
    }

    private function defaultSettings(): array
    {
        return [
            'seo_title' => '',
            'seo_description' => '',
            'allow_indexing' => true,
            'logo_path' => null,
            'show_nav' => true,
            'nav_cta' => '',
            'footer_text' => '',
            'notify_leads' => true,
            'notify_email' => '',
        ];
    }

    private function sanitizeSettings(array $input, LandingPage $page): array
    {
        $current = $this->settings($page);
        $text = fn ($key, $max) => Str::limit(trim((string) ($input[$key] ?? $current[$key] ?? '')), $max, '');
        $bool = fn ($key) => array_key_exists($key, $input) ? filter_var($input[$key], FILTER_VALIDATE_BOOL) : (bool) $current[$key];

        $logo = $current['logo_path'];
        if (filter_var($input['remove_logo'] ?? false, FILTER_VALIDATE_BOOL)) {
            $logo = null;
        } elseif (is_string($input['logo_path'] ?? null) && str_starts_with($input['logo_path'], $this->imagePrefix($page)) && ! str_contains($input['logo_path'], '..')) {
            $logo = $input['logo_path'];
        }
        $notify = trim((string) ($input['notify_email'] ?? $current['notify_email']));

        return [
            'seo_title' => $text('seo_title', 70),
            'seo_description' => $text('seo_description', 170),
            'allow_indexing' => $bool('allow_indexing'),
            'logo_path' => $logo,
            'show_nav' => $bool('show_nav'),
            'nav_cta' => $text('nav_cta', 30),
            'footer_text' => $text('footer_text', 200),
            'notify_leads' => $bool('notify_leads'),
            'notify_email' => filter_var($notify, FILTER_VALIDATE_EMAIL) ? $notify : '',
        ];
    }

    private function setPublished(LandingPage $page, bool $published): void
    {
        $page->is_published = $published;
        if ($published && ! $page->published_at) {
            $page->published_at = now();
        }
    }

    private function resolveImages(array $section, callable $imageUrl): array
    {
        $schema = Sections::TYPES[$section['type']];
        $data = $section['data'];
        foreach ($schema['fields'] as $name => $field) {
            if ($field['type'] === 'image') {
                $data[$name] = filled($data[$name]) ? $imageUrl($data[$name], $section['id'].'-'.$name) : null;
            }
        }
        if (isset($schema['items'])) {
            foreach ($data['items'] as $i => $item) {
                foreach ($schema['items']['fields'] as $name => $field) {
                    if ($field['type'] === 'image') {
                        $data['items'][$i][$name] = filled($item[$name]) ? $imageUrl($item[$name], $section['id'].'-'.$i.'-'.$name) : null;
                    }
                }
            }
        }

        return $data;
    }

    /** Ready-made values the section templates need (links, lists). */
    private function extras(array $section): array
    {
        $d = $section['data'];

        return match ($section['type']) {
            'contact' => [
                'phone_href' => filled($d['phone']) ? 'tel:'.preg_replace('/[^0-9+]/', '', $d['phone']) : null,
                'whatsapp_href' => filled($d['whatsapp']) && preg_replace('/\D/', '', $d['whatsapp']) !== '' ? 'https://wa.me/'.preg_replace('/\D/', '', $d['whatsapp']) : null,
                'map_embed' => $d['show_map'] && filled($d['address']) ? 'https://maps.google.com/maps?q='.rawurlencode($d['address']).'&output=embed' : null,
            ],
            'pricing' => ['items' => array_map(fn ($item) => $item + ['feature_list' => array_values(array_filter(array_map('trim', explode("\n", $item['features']))))], $d['items'])],
            default => [],
        };
    }

    /** @throws ValidationException */
    private function assertSlugAvailable(string $slug, ?LandingPage $ignore = null): void
    {
        if (! $this->slugAvailable($slug, $ignore)) {
            throw ValidationException::withMessages(['slug' => 'This web address is taken or not allowed. Try another one.']);
        }
    }
}
