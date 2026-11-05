<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class NfcCard extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    public const STATUSES = ['in_stock', 'assigned', 'active', 'sold', 'defective', 'lost', 'replaced', 'retired'];

    protected $fillable = ['card_inventory_id', 'card_identifier', 'uid', 'tag_type', 'status', 'batch_number', 'notes', 'encoded_at'];

    protected function casts(): array
    {
        return ['encoded_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(CardInventory::class, 'card_inventory_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(NfcAssignment::class)->latest('assigned_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(NfcAssignment::class)->whereNull('ended_at')->latestOfMany('assigned_at');
    }
}
