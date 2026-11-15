<?php

namespace App\Services\Assistant;

/** What the model returned once a streamed reply has finished. */
final readonly class AssistantReply
{
    public function __construct(
        public string $text,
        public ?string $model = null,
        public ?string $stopReason = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    public function refused(): bool
    {
        return $this->stopReason === 'refusal';
    }
}
