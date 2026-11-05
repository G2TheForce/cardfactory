<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DigitalProfile;
use App\Models\NfcCard;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Records public profile engagement without keeping personal visitor data:
 * no IP addresses, no full user agents, no cookies. Unique visitors are
 * counted with a salted hash that changes every day, so a visitor cannot be
 * followed from one day to the next. Do Not Track / Global Privacy Control
 * requests are counted without even that hash.
 */
class ProfileAnalytics
{
    /** Click events the public page may report, mapped to their event type. */
    public const CLICKS = [
        'website' => AnalyticsEvent::CLICK_WEBSITE,
        'whatsapp' => AnalyticsEvent::CLICK_WHATSAPP,
        'email' => AnalyticsEvent::CLICK_EMAIL,
        'phone' => AnalyticsEvent::CLICK_PHONE,
        'social' => AnalyticsEvent::CLICK_SOCIAL,
        'custom' => AnalyticsEvent::CLICK_CUSTOM,
    ];

    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|telegrambot|discordbot|curl|wget|python-requests|headless/i';

    public function record(
        string $type,
        DigitalProfile $profile,
        Request $request,
        ?string $source = null,
        ?string $target = null,
        ?QrCode $qr = null,
        ?NfcCard $nfc = null,
    ): ?AnalyticsEvent {
        $ua = (string) $request->userAgent();
        if ($ua === '' || preg_match(self::BOTS, $ua)) {
            return null;
        }

        $private = $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1';
        $hash = $private ? null : hash_hmac('sha256', $request->ip().'|'.$ua.'|'.now()->toDateString(), (string) config('app.key'));

        // A reload within 30 minutes is the same view, not a new one.
        if ($type === AnalyticsEvent::PROFILE_VIEW && $hash
            && ! Cache::add('pv:'.$profile->id.':'.$hash, 1, now()->addMinutes(30))) {
            return null;
        }

        $event = AnalyticsEvent::create([
            'customer_id' => $profile->customer_id,
            'digital_profile_id' => $profile->id,
            'qr_code_id' => $qr?->id,
            'nfc_card_id' => $nfc?->id,
            'event_type' => $type,
            'source' => $source,
            'target' => $target ? mb_substr($target, 0, 60) : null,
            'country_code' => $this->country($request),
            'device_type' => $this->device($ua),
            'referrer_host' => $this->referrer($request),
            'visitor_hash' => $hash,
            'occurred_at' => now(),
        ]);

        match ($type) {
            AnalyticsEvent::PROFILE_VIEW => $profile->increment('views_count'),
            AnalyticsEvent::QR_SCAN => $qr?->increment('scans_count'),
            default => null,
        };

        return $event;
    }

    /** Country from a trusted proxy header (e.g. Cloudflare's CF-IPCountry), when configured. */
    private function country(Request $request): ?string
    {
        $header = config('smartcard.analytics.country_header');
        $code = $header ? strtoupper((string) $request->header($header)) : '';

        return preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, ['XX', 'T1'], true) ? $code : null;
    }

    private function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    private function referrer(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);

        return $host && $host !== $request->getHost() ? mb_substr(strtolower($host), 0, 255) : null;
    }
}
