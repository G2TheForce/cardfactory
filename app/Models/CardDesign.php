<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardDesign extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'user_id', 'customer_id', 'card_theme_id', 'customer_import_id', 'brand_kit_id', 'designer_id', 'name', 'status',
        'width_mm', 'height_mm', 'bleed_mm', 'corner_radius_mm', 'orientation',
        'front', 'back', 'settings', 'front_thumbnail_path', 'back_thumbnail_path', 'version',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'bleed_mm' => 'decimal:2',
            'corner_radius_mm' => 'decimal:2',
            'front' => 'array',
            'back' => 'array',
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(CardTheme::class, 'card_theme_id');
    }

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(BrandKit::class);
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(CardElement::class)->orderBy('z_index');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(CardExport::class);
    }
}
