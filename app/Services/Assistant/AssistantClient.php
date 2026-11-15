<?php

namespace App\Services\Assistant;

use Generator;

/**
 * Talks to the language model behind the assistant. Bound to
 * ClaudeAssistantClient; tests bind a fake so no API calls are made.
 */
interface AssistantClient
{
    /** Whether an API key is set, so the assistant can be offered at all. */
    public function isConfigured(): bool;

    /**
     * Streams a reply. Yields text as it arrives and returns the finished reply.
     *
     * @param  list<array{role: string, content: string}>  $messages  oldest first, ending with the user's question
     * @return Generator<int, string, mixed, AssistantReply>
     *
     * @throws AssistantException when the reply could not be produced
     */
    public function stream(string $system, array $messages): Generator;
}
