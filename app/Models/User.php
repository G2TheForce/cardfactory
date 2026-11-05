<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUlid, HasRoles, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super-admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_DESIGNER = 'designer';

    public const ROLE_SALES_MANAGER = 'sales-manager';

    public const ROLE_CUSTOMER = 'customer';

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function createdCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    public function cardDesigns(): HasMany
    {
        return $this->hasMany(CardDesign::class);
    }

    public function brandKits(): HasMany
    {
        return $this->hasMany(BrandKit::class);
    }

    public function favoriteThemes(): BelongsToMany
    {
        return $this->belongsToMany(CardTheme::class, 'card_theme_favorites')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function uploadedAssets(): HasMany
    {
        return $this->hasMany(UploadedAsset::class);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_DESIGNER, self::ROLE_SALES_MANAGER]);
    }

    public function isAdministrator(): bool
    {
        return $this->hasAnyRole([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN]);
    }

    /** Roles this user may grant to others. Only a Super Admin can create Super Admins. */
    public function assignableRoles(): array
    {
        $roles = array_keys(config('smartcard.roles'));

        return $this->hasRole(self::ROLE_SUPER_ADMIN)
            ? $roles
            : array_values(array_diff($roles, [self::ROLE_SUPER_ADMIN]));
    }

    public function primaryRole(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
