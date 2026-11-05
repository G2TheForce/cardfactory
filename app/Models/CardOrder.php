<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardOrder extends Model
{
    use HasFactory, HasPublicUlid, SoftDeletes;

    public const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];

    public const REVENUE_STATUSES = ['paid', 'processing', 'shipped', 'completed'];

    protected $fillable = [
        'order_number', 'customer_id', 'created_by', 'status', 'items', 'quantity',
        'subtotal', 'tax', 'total', 'currency', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nfcCards(): BelongsToMany
    {
        return $this->belongsToMany(NfcCard::class)->withTimestamps();
    }
}
