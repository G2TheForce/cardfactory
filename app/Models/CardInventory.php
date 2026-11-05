<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardInventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'card_inventory';

    public const TAG_TYPES = ['NTAG213', 'NTAG215', 'NTAG216', 'MIFARE', 'OTHER'];

    protected $fillable = ['sku', 'name', 'tag_type', 'material', 'description', 'unit_price', 'cost_price', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Products are addressed by SKU in URLs, not by numeric id. */
    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    public function nfcCards(): HasMany
    {
        return $this->hasMany(NfcCard::class);
    }
}
