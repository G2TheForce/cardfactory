<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NfcAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'nfc_card_id', 'customer_id', 'digital_profile_id', 'assigned_by', 'replaces_assignment_id',
        'status', 'profile_url', 'reason', 'assigned_at', 'activated_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function nfcCard(): BelongsTo
    {
        return $this->belongsTo(NfcCard::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function digitalProfile(): BelongsTo
    {
        return $this->belongsTo(DigitalProfile::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_assignment_id');
    }
}
