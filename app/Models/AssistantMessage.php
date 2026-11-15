<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A question ("user") or reply ("assistant") in an assistant conversation. */
class AssistantMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = ['assistant_conversation_id', 'role', 'content', 'failed', 'model', 'input_tokens', 'output_tokens'];

    protected function casts(): array
    {
        return ['failed' => 'boolean'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}
