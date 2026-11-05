<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardExport extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'user_id', 'card_design_id', 'customer_import_id', 'format', 'sides', 'options', 'status',
        'disk', 'file_path', 'file_size', 'error', 'downloads_count', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cardDesign(): BelongsTo
    {
        return $this->belongsTo(CardDesign::class);
    }

    public function customerImport(): BelongsTo
    {
        return $this->belongsTo(CustomerImport::class);
    }
}
