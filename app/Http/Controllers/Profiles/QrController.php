<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Models\DigitalProfile;
use App\Services\DigitalProfileService;
use App\Services\QRCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** QR code style and downloads (PNG, SVG, vector PDF) for a profile. */
class QrController extends Controller
{
    /** Allowed sizes: pixels for PNG/SVG, millimetres for PDF. */
    public const SIZES = ['png' => [512, 1024, 2048, 4096], 'svg' => [512, 1024], 'pdf' => [25, 50, 100]];

    public function __construct(
        private readonly DigitalProfileService $profiles,
        private readonly QRCodeService $qr,
    ) {}

    public function update(Request $request, DigitalProfile $profile): RedirectResponse
    {
        $this->authorize('update', $profile);
        $data = $request->validate([
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background' => ['required_unless:transparent,1', 'nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'transparent' => ['nullable', 'boolean'],
            'margin' => ['required', 'integer', 'min:0', 'max:8'],
            'level' => ['required', Rule::in(QRCodeService::LEVELS)],
        ]);
        if ($request->boolean('transparent')) {
            $data['background'] = 'transparent';
        }
        unset($data['transparent']);

        $this->profiles->updateQrStyle($profile, $data, $request->user());

        return redirect()->to(route('profiles.show', $profile).'#qr')->with('success', 'QR code style saved.');
    }

    public function download(Request $request, DigitalProfile $profile, string $format): Response
    {
        $this->authorize('view', $profile);
        abort_unless(isset(self::SIZES[$format]), 404);
        $size = (int) $request->query('size', self::SIZES[$format][1]);
        abort_unless(in_array($size, self::SIZES[$format], true), 422, 'Unsupported size.');

        return $this->file($profile, $format, $size);
    }

    /** Shared with the API controller. */
    public function file(DigitalProfile $profile, string $format, int $size): Response
    {
        $qrCode = $this->profiles->qrCode($profile);
        $s = $this->qr->style($qrCode->style);
        $url = route('qr.scan', $qrCode->code);
        $name = $profile->slug.'-qr';

        return match ($format) {
            'png' => response($this->qr->png($url, $size, $s['color'], $s['background'], $s['margin'], $s['level']), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="'.$name.'-'.$size.'px.png"',
            ]),
            'svg' => response($this->qr->svg($url, $size, $s['color'], $s['background'], $s['margin'], $s['level']), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.$name.'.svg"',
            ]),
            'pdf' => response($this->qr->pdf($url, $size, $s['color'], $s['background'], $s['margin'], $s['level'], 'QR code for /card/'.$profile->slug), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'-'.$size.'mm.pdf"',
            ]),
        };
    }
}
