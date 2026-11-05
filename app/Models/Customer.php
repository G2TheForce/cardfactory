<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    protected $fillable = [
        'user_id', 'company_id', 'created_by', 'assigned_designer_id', 'customer_import_id',
        'full_name', 'professional_title', 'profile_photo_path', 'department', 'designation',
        'mobile', 'whatsapp', 'email', 'website', 'office_phone',
        'country', 'province', 'city', 'office_address', 'map_url', 'latitude', 'longitude',
        'social_links', 'business', 'custom_fields', 'custom_links',
        'introduction', 'portfolio_url', 'booking_url', 'visibility', 'status',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'business' => 'array',
            'custom_fields' => 'array',
            'custom_links' => 'array',
            'visibility' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedDesigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_designer_id');
    }

    public function cardDesigns(): HasMany
    {
        return $this->hasMany(CardDesign::class);
    }

    public function digitalProfiles(): HasMany
    {
        return $this->hasMany(DigitalProfile::class);
    }

    public function nfcAssignments(): HasMany
    {
        return $this->hasMany(NfcAssignment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CardOrder::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
