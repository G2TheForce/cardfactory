<?php

namespace Tests\Support;

use App\Services\Assistant\AssistantClient;
use App\Services\Assistant\AssistantException;
use App\Services\Assistant\AssistantReply;
use Generator;

/** Stands in for the Claude API: streams a canned reply and records what was sent. */
class FakeAssistantClient implements AssistantClient
{
    /** @var list<array{system: string, messages: array}> */
    public array $requests = [];

    public function __construct(
        public bool $configured = true,
        public string $reply = 'Open **Design → Exports** and choose PDF.',
        public ?string $stopReason = 'end_turn',
        public ?string $error = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function stream(string $system, array $messages): Generator
    {
        $this->requests[] = ['system' => $system, 'messages' => $messages];

        if ($this->error !== null) {
            throw new AssistantException($this->error);
        }

        foreach (str_split($this->reply, 8) as $chunk) {
            yield $chunk;
        }

        return new AssistantReply($this->reply, 'claude-opus-5-5', $this->stopReason, 1200, 40);
    }
}
