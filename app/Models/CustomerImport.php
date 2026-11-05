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
        'user_id', 'card_theme_id', 'card_design_id', 'original_filename', 'file_path', 'headers', 'column_mapping', 'options',
        'status', 'total_rows', 'valid_rows', 'failed_rows', 'processed_rows', 'generated_rows', 'errors', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
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

    public function templateDesign(): BelongsTo
    {
        return $this->belongsTo(CardDesign::class, 'card_design_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function cardDesigns(): HasMany
    {
        return $this->hasMany(CardDesign::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(CardExport::class);
    }
}
