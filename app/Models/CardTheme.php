<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardTheme extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'parent_id', 'created_by', 'name', 'slug', 'category', 'description', 'is_system',
        'status', 'is_premium', 'width_mm', 'height_mm', 'orientation', 'palette', 'fonts',
        'front', 'back', 'layout', 'thumbnail_path', 'sort_order', 'usage_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_premium' => 'boolean',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'palette' => 'array',
            'fonts' => 'array',
            'front' => 'array',
            'back' => 'array',
            'layout' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cardDesigns(): HasMany
    {
        return $this->hasMany(CardDesign::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_theme_favorites')->withTimestamps();
    }
}
