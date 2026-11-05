<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['digital_profile_id', 'card_design_id', 'code', 'target_url', 'style', 'scans_count', 'is_active'];

    protected function casts(): array
    {
        return [
            'style' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function digitalProfile(): BelongsTo
    {
        return $this->belongsTo(DigitalProfile::class);
    }

    public function cardDesign(): BelongsTo
    {
        return $this->belongsTo(CardDesign::class);
    }
}
