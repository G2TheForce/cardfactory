<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerImport extends Model
{
    use HasFactory, HasPublicUlid;

    protected $fillable = [
        'user_id', 'card_theme_id', 'original_filename', 'file_path', 'column_mapping', 'options',
        'status', 'total_rows', 'valid_rows', 'failed_rows', 'errors', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'options' => 'array',
            'errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(CardTheme::class, 'card_theme_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(CardExport::class);
    }
}
