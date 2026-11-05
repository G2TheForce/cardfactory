<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BrandKit extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'user_id', 'company_id', 'name', 'logo_path', 'primary_color', 'secondary_color',
        'accent_color', 'typography', 'background', 'company_details', 'social_links',
    ];

    protected function casts(): array
    {
        return [
            'typography' => 'array',
            'background' => 'array',
            'company_details' => 'array',
            'social_links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cardDesigns(): HasMany
    {
        return $this->hasMany(CardDesign::class);
    }
}
