<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\DigitalProfile;
use App\Models\QrCode;
use App\Services\DigitalProfileService;
use App\Services\NFCManagementService;
use App\Services\ProfileAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * The public side: profile pages, vCards, and the short QR / NFC links that
 * redirect to them. Nothing here needs a login.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private readonly DigitalProfileService $profiles,
        private readonly ProfileAnalytics $analytics,
    ) {}

    public function show(Request $request, string $slug): BaseResponse
    {
        $profile = $this->find($slug);
        if (! $profile) {
            return $this->unavailable();
        }

        // Staff and the owner looking at their own page aren't counted.
        $user = $request->user();
        if (! $user || ! $user->can('view', $profile)) {
            $via = $request->query('via');
            $this->analytics->record(AnalyticsEvent::PROFILE_VIEW, $profile, $request, in_array($via, ['qr', 'nfc'], true) ? $via : ($request->headers->get('referer') ? 'link' : 'direct'));
        }

        return response()->view($this->profiles->publicView($profile), [
            'profile' => $profile,
            'p' => $this->profiles->publicData($profile),
            'urls' => $this->profiles->urls($profile),
            'preview' => false,
            'available' => true,
        ])->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function vcard(Request $request, string $slug): BaseResponse
    {
        $profile = $this->find($slug);
        if (! $profile) {
            return $this->unavailable();
        }
        $this->analytics->record(AnalyticsEvent::VCARD_DOWNLOAD, $profile, $request, 'direct');

        return response($this->profiles->vcard($profile), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$this->profiles->vcardFilename($profile).'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Click beacons from the public page (sent with navigator.sendBeacon). */
    public function track(Request $request, string $slug): Response
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ProfileAnalytics::CLICKS))],
            'target' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
        ]);
        $profile = $this->find($slug);
        if ($profile) {
            $this->analytics->record(ProfileAnalytics::CLICKS[$data['type']], $profile, $request, null, $data['target'] ?? null);
        }

        return response()->noContent();
    }

    /** /q/{code}: the URL inside every QR code. */
    public function qr(Request $request, string $code): BaseResponse
    {
        $qr = QrCode::where('code', strtolower($code))->where('is_active', true)->first();
        $profile = $qr?->digitalProfile()->with('customer')->first();
        if (! $profile || ! $this->profiles->isAvailable($profile)) {
            return $this->unavailable();
        }
        $this->analytics->record(AnalyticsEvent::QR_SCAN, $profile, $request, 'qr', null, $qr);

        return $this->redirect($profile, 'qr');
    }

    /** /c/{identifier}: the profile's own NFC-friendly link, for tags people write themselves. */
    public function nfcLink(Request $request, string $identifier): BaseResponse
    {
        $profile = DigitalProfile::where('card_identifier', strtoupper($identifier))->with('customer')->first();
        if (! $profile || ! $this->profiles->isAvailable($profile)) {
            return $this->unavailable();
        }
        $this->analytics->record(AnalyticsEvent::NFC_TAP, $profile, $request, 'nfc');

        return $this->redirect($profile, 'nfc');
    }

    /** /n/{card}: the permanent URL written onto a physical NFC card. */
    public function nfcTap(Request $request, string $identifier, NFCManagementService $nfc): BaseResponse
    {
        $tap = $nfc->resolveTap($identifier);
        $profile = $tap ? $tap['assignment']->digitalProfile()->with('customer')->first() : null;
        if (! $profile || ! $this->profiles->isAvailable($profile)) {
            return $this->unavailable('This card isn\'t linked to an active profile right now.');
        }
        $this->analytics->record(AnalyticsEvent::NFC_TAP, $profile, $request, 'nfc', null, null, $tap['card']);

        return $this->redirect($profile, 'nfc');
    }

    private function find(string $slug): ?DigitalProfile
    {
        $profile = DigitalProfile::where('slug', strtolower($slug))->with('customer.company')->first();

        return $profile && $this->profiles->isAvailable($profile) ? $profile : null;
    }

    private function redirect(DigitalProfile $profile, string $via): RedirectResponse
    {
        // 302, never 301: the destination can change when a card is reassigned.
        return redirect()->to(route('profiles.public', $profile->slug).'?via='.$via, 302)->header('Cache-Control', 'no-store');
    }

    private function unavailable(?string $message = null): Response
    {
        return response()->view('profiles.unavailable', ['message' => $message], 404);
    }
}
