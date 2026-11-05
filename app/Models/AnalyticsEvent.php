<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const PROFILE_VIEW = 'profile_view';

    public const QR_SCAN = 'qr_scan';

    public const NFC_TAP = 'nfc_tap';

    public const VCARD_DOWNLOAD = 'vcard_download';

    public const CARD_DOWNLOAD = 'card_download';

    public const CLICK_WEBSITE = 'click_website';

    public const CLICK_WHATSAPP = 'click_whatsapp';

    public const CLICK_EMAIL = 'click_email';

    public const CLICK_PHONE = 'click_phone';

    public const CLICK_SOCIAL = 'click_social';

    public const CLICK_CUSTOM = 'click_custom';

    protected $fillable = [
        'customer_id', 'digital_profile_id', 'qr_code_id', 'nfc_card_id', 'event_type', 'source',
        'target', 'country_code', 'device_type', 'referrer_host', 'visitor_hash', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function digitalProfile(): BelongsTo
    {
        return $this->belongsTo(DigitalProfile::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function nfcCard(): BelongsTo
    {
        return $this->belongsTo(NfcCard::class);
    }
}
