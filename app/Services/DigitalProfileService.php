<?php

namespace App\Services;

use App\Models\CardDesign;
use App\Models\Customer;
use App\Models\DigitalProfile;
use App\Models\QrCode;
use App\Models\User;
use App\Services\Social\SocialStatsService;
use App\Support\CustomerFields;
use App\Support\ProfileCodes;
use App\Support\ProfileThemes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Public digital business card profiles: creation, appearance and layout,
 * the data shown on the public page (respecting the customer's visibility
 * choices), and the vCard download.
 */
class DigitalProfileService
{
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])$/';

    public function __construct(
        private readonly AssetService $assets,
        private readonly ActivityLogger $logger,
        private readonly QRCodeService $qr,
    ) {}

    /**
     * @param  array{slug?: ?string, profile_theme?: ?string, card_design?: ?CardDesign}  $data
     */
    public function create(Customer $customer, array $data, User $actor): DigitalProfile
    {
        $theme = isset(ProfileThemes::THEMES[$data['profile_theme'] ?? '']) ? $data['profile_theme'] : 'classic';
        $slug = ($data['slug'] ?? null) ?: $this->suggestSlug($customer->full_name);
        $this->assertSlugAvailable($slug);

        return DB::transaction(function () use ($customer, $data, $actor, $theme, $slug) {
            $profile = DigitalProfile::create([
                'customer_id' => $customer->id,
                'card_design_id' => $this->designFor($customer, $data['card_design'] ?? null)?->id,
                'slug' => $slug,
                'card_identifier' => ProfileCodes::profileIdentifier(),
                'profile_theme' => $theme,
                'layout' => ProfileThemes::defaultLayout($theme),
                'settings' => $this->defaultSettings($theme),
                'is_active' => true,
                'published_at' => now(),
            ]);
            $this->qrCode($profile);
            $this->logger->log('profile.created', $profile, 'Created digital profile /card/'.$profile->slug.' for "'.$customer->full_name.'"', [], $actor->id);

            return $profile;
        });
    }

    /**
     * Update appearance, layout, buttons, portfolio and settings.
     *
     * @param  array<string, mixed>  $data  validated ProfileRequest data
     * @param  array{cover?: ?UploadedFile, portfolio?: array<int, UploadedFile>}  $files
     */
    public function update(DigitalProfile $profile, array $data, User $actor, array $files = []): DigitalProfile
    {
        if (isset($data['slug']) && $data['slug'] !== $profile->slug) {
            $this->assertSlugAvailable($data['slug'], $profile);
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($profile, $data, $actor, $files, &$stored) {
                $settings = $profile->settings ?? [];
                $themeChanged = isset($data['profile_theme']) && $data['profile_theme'] !== $profile->profile_theme;

                if (isset($data['slug'])) {
                    $profile->slug = $data['slug'];
                }
                if (isset($data['profile_theme'])) {
                    $profile->profile_theme = $data['profile_theme'];
                }
                if (array_key_exists('accent', $data)) {
                    // An empty accent means "use the theme's own colour".
                    $settings['accent'] = $data['accent'] ?: ProfileThemes::get($profile->profile_theme)['accent'];
                } elseif ($themeChanged) {
                    $settings['accent'] = ProfileThemes::get($profile->profile_theme)['accent'];
                }
                foreach (['button_style', 'headline', 'greeting', 'motto', 'social_style', 'header_style', 'font', 'background', 'card_style', 'effect'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $settings[$key] = $data[$key] ?: null;
                    }
                }
                if ($themeChanged && ! array_key_exists('background', $data)) {
                    $settings['background'] = null;
                }
                if (array_key_exists('glow', $data)) {
                    $settings['glow'] = $data['glow'] === null || $data['glow'] === '' ? null : (bool) $data['glow'];
                }
                foreach (['background_overlay', 'background_blur'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $settings[$key] = (int) $data[$key];
                    }
                }
                if (array_key_exists('social_links', $data) && $profile->customer) {
                    $links = collect((array) $data['social_links'])
                        ->only(array_keys(CustomerFields::SOCIAL))
                        ->map(fn ($v, $network) => SocialStatsService::normaliseLink($network, $v))
                        ->filter()->all();
                    $profile->customer->forceFill(['social_links' => $links ?: null])->save();
                    $profile->unsetRelation('customer');
                }
                if (array_key_exists('social_manual', $data)) {
                    $counts = $settings['social_counts'] ?? [];
                    foreach ((array) $data['social_manual'] as $network => $value) {
                        if (! isset(CustomerFields::SOCIAL[$network])) {
                            continue;
                        }
                        $counts[$network] = ($counts[$network] ?? ['count' => null, 'fetched_at' => null, 'error' => null])
                            + ['count' => null, 'fetched_at' => null, 'error' => null];
                        $counts[$network]['manual'] = $value === null || $value === '' ? null : (int) $value;
                    }
                    $settings['social_counts'] = $counts;
                }
                foreach (['allow_indexing', 'show_save_contact', 'show_qr', 'map_embed', 'show_follower_counts'] as $key) {
                    if (array_key_exists($key, $data)) {
                        $settings[$key] = (bool) $data[$key];
                    }
                }
                if (array_key_exists('layout', $data)) {
                    $profile->layout = ProfileThemes::normaliseLayout($data['layout']);
                }
                if (array_key_exists('ctas', $data)) {
                    $settings['ctas'] = array_values(array_map(fn ($c) => [
                        'label' => trim($c['label']), 'url' => trim($c['url']), 'style' => ($c['style'] ?? 'solid') === 'outline' ? 'outline' : 'solid',
                    ], $data['ctas'] ?? []));
                }
                if (array_key_exists('is_active', $data)) {
                    $profile->is_active = (bool) $data['is_active'];
                    $profile->published_at ??= $profile->is_active ? now() : null;
                }
                if (array_key_exists('card_design', $data)) {
                    $profile->card_design_id = $this->designFor($profile->customer, $data['card_design'])?->id;
                }

                // Cover image
                if (! empty($data['remove_cover']) && ! empty($settings['cover_path'])) {
                    $old = $settings['cover_path'];
                    $settings['cover_path'] = null;
                    DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                }
                if (($cover = $files['cover'] ?? null) instanceof UploadedFile) {
                    $old = $settings['cover_path'] ?? null;
                    $settings['cover_path'] = $stored[] = $this->assets->storeImageFile($cover, 'profiles/'.$profile->ulid, 1600, 'cover')['path'];
                    if ($old) {
                        DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                    }
                }

                // Page background image
                if (! empty($data['remove_background_image']) && ! empty($settings['background_image_path'])) {
                    $old = $settings['background_image_path'];
                    $settings['background_image_path'] = null;
                    DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                }
                if (($bgImage = $files['background_image'] ?? null) instanceof UploadedFile) {
                    $old = $settings['background_image_path'] ?? null;
                    $settings['background_image_path'] = $stored[] = $this->assets->storeImageFile($bgImage, 'profiles/'.$profile->ulid, 2000, 'background')['path'];
                    if ($old) {
                        DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                    }
                }

                $settings['portfolio'] = $this->portfolio($profile, $settings['portfolio'] ?? [], $data, $files['portfolio'] ?? [], $stored);

                $profile->settings = $settings;
                $profile->save();
                $this->syncQrTarget($profile);
                $this->logger->log('profile.updated', $profile, 'Updated digital profile /card/'.$profile->slug, [], $actor->id);

                return $profile;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($stored);
            throw $e;
        }
    }

    public function delete(DigitalProfile $profile, User $actor): void
    {
        DB::transaction(function () use ($profile, $actor) {
            // Physical cards stop pointing at a deleted profile.
            app(NFCManagementService::class)->releaseAll($profile, $actor, 'Profile deleted');
            $profile->qrCodes()->update(['is_active' => false]);
            $this->logger->log('profile.deleted', $profile, 'Deleted digital profile /card/'.$profile->slug, [], $actor->id);
            $profile->delete();
        });
    }

    /** The profile's QR code record (created on first use). */
    public function qrCode(DigitalProfile $profile): QrCode
    {
        return $profile->qrCodes()->oldest('id')->first() ?? $profile->qrCodes()->create([
            'code' => ProfileCodes::qrCode(),
            'target_url' => $this->publicUrl($profile),
            'style' => QRCodeService::DEFAULT_STYLE,
            'is_active' => true,
        ]);
    }

    /** @throws ValidationException */
    public function updateQrStyle(DigitalProfile $profile, array $style, User $actor): QrCode
    {
        $qr = $this->qrCode($profile);
        $style = $this->qr->style($style);
        if ($style['background'] !== 'transparent' && $this->qr->contrast($style['color'], $style['background']) < 3) {
            throw ValidationException::withMessages(['color' => 'These colours are too close together for phones to scan reliably. Pick a darker code colour or a lighter background.']);
        }
        $qr->update(['style' => $style]);
        $this->logger->log('profile.qr_updated', $profile, 'Changed the QR code style for /card/'.$profile->slug, [], $actor->id);

        return $qr;
    }

    /** Whether the public page, QR and NFC links should resolve. */
    public function isAvailable(DigitalProfile $profile): bool
    {
        $customer = $profile->customer;

        return $profile->is_active && ! $profile->trashed() && $customer && ! $customer->trashed() && $customer->status === 'active';
    }

    /** @return array{public: string, qr: string, nfc: string, vcard: string} */
    public function urls(DigitalProfile $profile): array
    {
        return [
            'public' => $this->publicUrl($profile),
            'qr' => route('qr.scan', $this->qrCode($profile)->code),
            'nfc' => route('profiles.nfc-link', $profile->card_identifier),
            'vcard' => route('profiles.vcard', $profile->slug),
        ];
    }

    /** The Blade view for the public page: a phone card, or a Profile Studio one-page site. */
    public function publicView(DigitalProfile $profile): string
    {
        return ProfileThemes::isStudio($profile->profile_theme) ? 'profiles.public-studio' : 'profiles.public';
    }

    public function publicUrl(DigitalProfile $profile): string
    {
        return route('profiles.public', $profile->slug);
    }

    public function suggestSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 50, '') ?: 'card';
        if (strlen($base) < 3) {
            $base .= '-card';
        }
        $slug = $base;
        $i = 2;
        while (! $this->slugAvailable($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function slugAvailable(string $slug, ?DigitalProfile $ignore = null): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1
            && ! in_array($slug, config('smartcard.profiles.reserved_slugs', []), true)
            && ! DigitalProfile::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))->exists();
    }

    /**
     * Everything the public page shows, already filtered by the customer's
     * "show on profile" choices.
     */
    public function publicData(DigitalProfile $profile): array
    {
        $profile->loadMissing('customer.company');
        $c = $profile->customer;
        $company = $c->company;
        $show = fn (string $field) => CustomerFields::visible($c->visibility, 'profile', $field);
        $disk = Storage::disk('public');
        $settings = $this->settings($profile);
        $theme = ProfileThemes::resolve($profile->profile_theme, $settings);
        $digits = fn (?string $v) => $v ? preg_replace('/[^\d+]/', '', $v) : null;

        $mobile = $show('mobile') ? $c->mobile : null;
        $whatsapp = $show('whatsapp') ? ($c->whatsapp ?: $c->mobile) : null;
        $website = $show('website') ? ($c->website ?: $company?->website) : null;
        $address = collect([
            $show('office_address') ? $c->office_address : null,
            $show('city') ? $c->city : null,
            $c->province && $show('city') ? $c->province : null,
            $show('country') ? $c->country : null,
        ])->filter()->implode(', ');

        $mapQuery = $c->latitude && $c->longitude ? $c->latitude.','.$c->longitude : ($address ?: null);

        return [
            'name' => $c->full_name,
            'title' => $show('professional_title') ? ($c->professional_title ?: ($show('designation') ? $c->designation : null)) : null,
            'designation' => $show('designation') && $c->designation !== $c->professional_title ? $c->designation : null,
            'department' => $show('department') ? $c->department : null,
            'headline' => $settings['headline'],
            'greeting' => $settings['greeting'],
            'motto' => $settings['motto'],
            'company' => $company?->name ?? ($c->business['company_name'] ?? null),
            'company_logo' => $company?->logo_path ? $disk->url($company->logo_path) : null,
            'photo' => $show('photo') && $c->profile_photo_path ? $disk->url($c->profile_photo_path) : null,
            'cover' => $settings['cover_path'] ? $disk->url($settings['cover_path']) : null,
            'background_image' => $settings['background_image_path'] ? $disk->url($settings['background_image_path']) : null,
            'background_overlay' => max(0, min(90, (int) $settings['background_overlay'])),
            'background_blur' => max(0, min(20, (int) $settings['background_blur'])),
            'initials' => collect(preg_split('/\s+/', trim($c->full_name)) ?: [])->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''),
            'intro' => $c->introduction,
            'mobile' => $mobile,
            'phone_href' => $mobile ? 'tel:'.$digits($mobile) : null,
            'office_phone' => $show('office_phone') ? $c->office_phone : null,
            'office_href' => $show('office_phone') && $c->office_phone ? 'tel:'.$digits($c->office_phone) : null,
            'whatsapp' => $whatsapp,
            'whatsapp_href' => $whatsapp ? 'https://wa.me/'.ltrim((string) $digits($whatsapp), '+') : null,
            'email' => $show('email') ? $c->email : null,
            'website' => $website,
            'website_label' => $website ? preg_replace('#^https?://(www\.)?#', '', rtrim($website, '/')) : null,
            'address' => $address ?: null,
            'map_url' => $mapQuery ? ($c->map_url ?: 'https://www.google.com/maps/search/?api=1&query='.urlencode($mapQuery)) : null,
            'map_embed' => $mapQuery && $settings['map_embed'] ? 'https://www.google.com/maps?q='.urlencode($mapQuery).'&output=embed' : null,
            'social' => $show('social_links') ? $this->socialLinks($c->social_links ?? [], $settings) : [],
            'social_style' => $settings['social_style'],
            'links' => collect([
                $c->portfolio_url ? ['label' => 'Portfolio', 'url' => $c->portfolio_url, 'icon' => 'briefcase'] : null,
                $c->booking_url ? ['label' => 'Book a meeting', 'url' => $c->booking_url, 'icon' => 'calendar-check'] : null,
            ])->filter()->merge(collect($c->custom_links ?? [])->map(fn ($l) => ['label' => $l['label'], 'url' => $l['url'], 'icon' => 'link']))->values()->all(),
            'custom_fields' => $c->custom_fields ?? [],
            'services' => $c->business['services'] ?? [],
            'description' => $c->business['description'] ?? ($company?->description ?? null),
            'hours' => $c->business['hours'] ?? [],
            'ctas' => $settings['ctas'],
            'portfolio' => array_map(fn ($p) => $p + ['url' => $disk->url($p['path'])], $settings['portfolio']),
            'theme' => $theme,
            'theme_key' => $profile->profile_theme,
            'accent' => $settings['accent'],
            'accent_text' => $this->qr->contrast($settings['accent'], '#ffffff') >= 3 ? '#ffffff' : '#111827',
            'button_style' => $settings['button_style'],
            'show_save_contact' => $settings['show_save_contact'],
            'show_qr' => $settings['show_qr'],
            'allow_indexing' => $settings['allow_indexing'],
            'sections' => collect(ProfileThemes::normaliseLayout($profile->layout))->where('enabled', true)->pluck('key')->all(),
        ];
    }

    /**
     * Social links in display order with button wording, brand colour and the
     * follower count to show (when counts are switched on and known).
     */
    private function socialLinks(array $links, array $settings): array
    {
        return collect(CustomerFields::SOCIAL)->map(function ($label, $key) use ($links, $settings) {
            if (! ($links[$key] ?? null)) {
                return null;
            }
            $net = SocialStatsService::NETWORKS[$key] ?? ['verb' => 'Follow', 'noun' => 'followers', 'color' => null];
            $count = $settings['show_follower_counts'] ? SocialStatsService::displayCount($settings['social_counts'] ?? [], $key) : null;

            return [
                'key' => $key, 'label' => $label, 'url' => $links[$key],
                'verb' => $net['verb'], 'color' => $net['color'], 'background' => $net['gradient'] ?? $net['color'], 'text' => $net['text'] ?? '#ffffff',
                'count' => $count, 'count_label' => $count !== null ? SocialStatsService::format($count).' '.$net['noun'] : null,
            ];
        })->filter()->values()->all();
    }

    /** Settings with defaults filled in. */
    public function settings(DigitalProfile $profile): array
    {
        return array_merge($this->defaultSettings($profile->profile_theme), array_filter($profile->settings ?? [], fn ($v) => $v !== null));
    }

    /** vCard 3.0 text for "Save contact". Only includes details shown on the profile. */
    public function vcard(DigitalProfile $profile): string
    {
        $d = $this->publicData($profile);
        $c = $profile->customer;
        $parts = preg_split('/\s+/', trim($d['name'])) ?: [];
        $last = count($parts) > 1 ? array_pop($parts) : '';
        $first = implode(' ', $parts);

        $lines = ['BEGIN:VCARD', 'VERSION:3.0'];
        $lines[] = 'N:'.$this->vEscape($last).';'.$this->vEscape($first).';;;';
        $lines[] = 'FN:'.$this->vEscape($d['name']);
        if ($d['company']) {
            $lines[] = 'ORG:'.$this->vEscape($d['company']).($d['department'] ? ';'.$this->vEscape($d['department']) : '');
        }
        if ($d['title']) {
            $lines[] = 'TITLE:'.$this->vEscape($d['title']);
        }
        if ($d['mobile']) {
            $lines[] = 'TEL;TYPE=CELL,VOICE:'.$this->vEscape($d['mobile']);
        }
        if ($d['office_phone']) {
            $lines[] = 'TEL;TYPE=WORK,VOICE:'.$this->vEscape($d['office_phone']);
        }
        if ($d['email']) {
            $lines[] = 'EMAIL;TYPE=INTERNET,WORK:'.$this->vEscape($d['email']);
        }
        if ($d['website']) {
            $lines[] = 'URL;TYPE=WORK:'.$this->vEscape($d['website']);
        }
        $lines[] = 'URL;TYPE=PROFILE:'.$this->vEscape($this->publicUrl($profile));
        if ($d['address']) {
            $show = fn ($f) => CustomerFields::visible($c->visibility, 'profile', $f);
            $lines[] = 'ADR;TYPE=WORK:;;'.$this->vEscape($show('office_address') ? (string) $c->office_address : '').';'
                .$this->vEscape($show('city') ? (string) $c->city : '').';'.$this->vEscape($show('city') ? (string) $c->province : '').';;'
                .$this->vEscape($show('country') ? (string) $c->country : '');
        }
        foreach ($d['social'] as $s) {
            $lines[] = 'X-SOCIALPROFILE;TYPE='.$s['key'].':'.$this->vEscape($s['url']);
        }
        if ($d['intro']) {
            $lines[] = 'NOTE:'.$this->vEscape(Str::limit($d['intro'], 500));
        }
        if ($d['photo'] && $c->profile_photo_path && ($photo = $this->vcardPhoto($c->profile_photo_path))) {
            $lines[] = 'PHOTO;ENCODING=b;TYPE=JPEG:'.$photo;
        }
        $lines[] = 'REV:'.$profile->updated_at?->utc()->format('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        return implode("\r\n", array_map(fn ($l) => $this->fold($l), $lines))."\r\n";
    }

    public function vcardFilename(DigitalProfile $profile): string
    {
        return (Str::slug($profile->customer->full_name) ?: 'contact').'.vcf';
    }

    private function defaultSettings(?string $theme): array
    {
        return [
            'accent' => ProfileThemes::get($theme)['accent'],
            // Profile Studio sites use pill buttons like the original design.
            'button_style' => ProfileThemes::isStudio($theme) ? 'pill' : 'rounded',
            'headline' => null,
            // Profile Studio extras: the line above the name and the motto band.
            'greeting' => null,
            'motto' => null,
            'cover_path' => null,
            'show_save_contact' => true,
            'show_qr' => true,
            'map_embed' => false,
            // Profiles are hidden from search engines unless the owner opts in.
            'allow_indexing' => false,
            'ctas' => [],
            'portfolio' => [],
            'social_style' => 'buttons',
            'show_follower_counts' => true,
            'social_counts' => [],
            // Overrides on top of the theme (null = use the theme's own).
            'header_style' => null,
            'font' => null,
            'background' => null,
            'background_image_path' => null,
            'background_overlay' => 40,
            'background_blur' => 0,
            'card_style' => null,
            'effect' => null,
            'glow' => null,
        ];
    }

    /** Keep, re-caption, remove and add portfolio images. */
    private function portfolio(DigitalProfile $profile, array $current, array $data, array $uploads, array &$stored): array
    {
        $remove = array_flip($data['portfolio_remove'] ?? []);
        $captions = $data['portfolio_captions'] ?? [];
        $items = [];
        foreach ($current as $item) {
            if (isset($remove[$item['id']])) {
                $path = $item['path'];
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));

                continue;
            }
            if (array_key_exists($item['id'], $captions)) {
                $item['caption'] = trim((string) $captions[$item['id']]) ?: null;
            }
            $items[] = $item;
        }

        foreach ($uploads as $file) {
            if (count($items) >= ProfileThemes::MAX_PORTFOLIO) {
                throw ValidationException::withMessages(['portfolio' => 'A profile can show up to '.ProfileThemes::MAX_PORTFOLIO.' portfolio images.']);
            }
            $img = $this->assets->storeImageFile($file, 'profiles/'.$profile->ulid.'/portfolio', 1600, 'portfolio');
            $stored[] = $img['path'];
            $items[] = ['id' => Str::lower(Str::random(8)), 'path' => $img['path'], 'caption' => null, 'width' => $img['width'], 'height' => $img['height']];
        }

        return $items;
    }

    private function designFor(?Customer $customer, ?CardDesign $design): ?CardDesign
    {
        if (! $design) {
            return null;
        }
        if (! $customer || (int) $design->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(['card_design' => 'Pick a card design that belongs to this customer.']);
        }

        return $design;
    }

    private function syncQrTarget(DigitalProfile $profile): void
    {
        $this->qrCode($profile)->update(['target_url' => $this->publicUrl($profile)]);
    }

    /** @throws ValidationException */
    private function assertSlugAvailable(string $slug, ?DigitalProfile $ignore = null): void
    {
        if (! $this->slugAvailable($slug, $ignore)) {
            throw ValidationException::withMessages(['slug' => 'This web address is taken or not allowed. Try another.']);
        }
    }

    private function vcardPhoto(string $path): ?string
    {
        try {
            $bytes = Storage::disk('public')->get($path);
            if (! $bytes) {
                return null;
            }

            return base64_encode((string) (new ImageManager(new Driver))->read($bytes)->scaleDown(400, 400)->toJpeg(80));
        } catch (\Throwable) {
            return null;
        }
    }

    private function vEscape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\n", ',', ';'], ['\\\\', '\\n', '\\n', '\\,', '\\;'], $value);
    }

    /** Fold lines longer than 75 octets (RFC 6350 §3.2) without splitting UTF-8 characters. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            $limit = $out === '' ? 75 : 74;
            if (strlen($current) + strlen($char) > $limit) {
                $out .= ($out === '' ? '' : "\r\n ").$current;
                $current = '';
            }
            $current .= $char;
        }

        return $out.($out === '' ? '' : "\r\n ").$current;
    }
}
