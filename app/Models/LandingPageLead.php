<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An enquiry a visitor sent through a landing page's contact form. */
class LandingPageLead extends Model
{
    use HasPublicUlid;

    protected $fillable = ['landing_page_id', 'name', 'email', 'phone', 'message', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
