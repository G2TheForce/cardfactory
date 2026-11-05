<?php

namespace App\Http\Resources;

use App\Models\DigitalProfile;
use App\Services\DigitalProfileService;
use App\Services\NFCManagementService;
use App\Support\ProfileThemes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DigitalProfile */
class DigitalProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(DigitalProfileService::class);
        $settings = $service->settings($this->resource);
        $qr = $service->qrCode($this->resource);

        return [
            'id' => $this->ulid,
            'slug' => $this->slug,
            'card_identifier' => $this->card_identifier,
            'is_active' => $this->is_active,
            'available' => $service->isAvailable($this->resource),
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->ulid, 'full_name' => $this->customer->full_name]),
            'theme' => $this->profile_theme,
            'accent' => $settings['accent'],
            'button_style' => $settings['button_style'],
            'headline' => $settings['headline'],
            'layout' => ProfileThemes::normaliseLayout($this->layout),
            'ctas' => $settings['ctas'],
            'show_save_contact' => $settings['show_save_contact'],
            'show_qr' => $settings['show_qr'],
            'map_embed' => $settings['map_embed'],
            'allow_indexing' => $settings['allow_indexing'],
            'urls' => $service->urls($this->resource) + [
                'qr_png' => route('api.v1.profiles.qr', [$this->resource, 'png']),
                'qr_svg' => route('api.v1.profiles.qr', [$this->resource, 'svg']),
                'qr_pdf' => route('api.v1.profiles.qr', [$this->resource, 'pdf']),
            ],
            'stats' => ['views' => (int) $this->views_count, 'qr_scans' => (int) $qr->scans_count],
            'nfc_cards' => app(NFCManagementService::class)->cardsFor($this->resource)->map(fn ($a) => [
                'card_identifier' => $a->nfcCard->card_identifier,
                'uid' => $a->nfcCard->uid,
                'tag_type' => $a->nfcCard->tag_type,
                'status' => $a->status,
                'tag_url' => $a->profile_url,
                'assigned_at' => $a->assigned_at?->toIso8601String(),
                'activated_at' => $a->activated_at?->toIso8601String(),
            ])->values(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
