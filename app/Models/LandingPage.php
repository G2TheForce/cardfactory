<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A multi-section marketing page for a customer's business, built in the
 * landing page designer and published at /p/{slug} or exported as a static site.
 */
class LandingPage extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'customer_id', 'brand_kit_id', 'created_by', 'name', 'slug', 'template', 'sections', 'style', 'settings',
        'is_published', 'published_at', 'views_count',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'style' => 'array',
            'settings' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(BrandKit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(LandingPageLead::class);
    }
}
