<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardElement extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = ['card_design_id', 'side', 'type', 'name', 'properties', 'z_index', 'is_locked', 'is_hidden'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'is_locked' => 'boolean',
            'is_hidden' => 'boolean',
        ];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(CardDesign::class, 'card_design_id');
    }
}
