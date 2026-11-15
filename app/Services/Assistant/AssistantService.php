<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\Assistant\KnowledgeBase;
use Generator;
use Illuminate\Support\Str;

/**
 * The in-app AI assistant: answers how-to questions about SmartCard Studio
 * and writes copy (bios, taglines, landing page text). Conversations are
 * stored per user; each question is sent with the recent history.
 */
class AssistantService
{
    public function __construct(
        private readonly AssistantClient $client,
        private readonly KnowledgeBase $knowledge,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /** Messages this user may still send today, or null when there is no limit. */
    public function remainingToday(User $user): ?int
    {
        $limit = (int) config('smartcard.assistant.daily_message_limit');
        if ($limit <= 0) {
            return null;
        }

        $sent = AssistantMessage::query()
            ->where('role', AssistantMessage::ROLE_USER)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return max(0, $limit - $sent);
    }

    public function startConversation(User $user, string $firstMessage): AssistantConversation
    {
        return AssistantConversation::create([
            'user_id' => $user->id,
            'title' => Str::limit(Str::squish($firstMessage), 80, '…'),
        ]);
    }

    /**
     * Saves the user's question, streams the reply as it is written and
     * saves the reply once it is complete (or failed).
     *
     * @param  string|null  $page  the page the user is on, e.g. "Landing pages › Edit", for context
     * @return Generator<int, string>
     */
    public function reply(AssistantConversation $conversation, string $question, ?string $page = null): Generator
    {
        $history = $this->history($conversation);

        $conversation->messages()->create(['role' => AssistantMessage::ROLE_USER, 'content' => $question]);
        $conversation->touch();

        $prompt = filled($page) ? "[I'm on this page: ".Str::limit(Str::squish($page), 160)."]\n\n".$question : $question;
        $messages = [...$history, ['role' => AssistantMessage::ROLE_USER, 'content' => $prompt]];

        $text = '';
        try {
            $stream = $this->client->stream($this->knowledge->systemPrompt($conversation->user), $messages);
            foreach ($stream as $chunk) {
                $text .= $chunk;
                yield $chunk;
            }
            $reply = $stream->getReturn();
        } catch (AssistantException $e) {
            $notice = ($text === '' ? '' : "\n\n").$e->getMessage();
            yield $notice;
            $this->saveReply($conversation, $text.$notice, failed: true);

            return;
        }

        if ($reply->refused()) {
            $notice = ($text === '' ? '' : "\n\n").'Sorry, I can\'t help with that request. Try asking in a different way.';
            yield $notice;
            $this->saveReply($conversation, $text.$notice, failed: true, reply: $reply);

            return;
        }

        $empty = $text === '';
        if ($empty) {
            $text = 'No reply was received. Please try again.';
            yield $text;
        }

        $this->saveReply($conversation, $text, failed: $empty, reply: $reply);
    }

    /**
     * Recent successful turns, oldest first. A question whose reply failed is
     * left out together with the reply, so roles keep alternating.
     *
     * @return list<array{role: string, content: string}>
     */
    private function history(AssistantConversation $conversation): array
    {
        $messages = $conversation->messages()->reorder()->latest('id')
            ->limit((int) config('smartcard.assistant.history_messages', 20))
            ->get(['id', 'role', 'content', 'failed'])
            ->reverse()->values();

        $turns = [];
        foreach ($messages as $i => $message) {
            $next = $messages[$i + 1] ?? null;
            if ($message->role === AssistantMessage::ROLE_USER && $next?->role === AssistantMessage::ROLE_ASSISTANT && ! $next->failed) {
                $turns[] = ['role' => AssistantMessage::ROLE_USER, 'content' => $message->content];
                $turns[] = ['role' => AssistantMessage::ROLE_ASSISTANT, 'content' => $next->content];
            }
        }

        return $turns;
    }

    private function saveReply(AssistantConversation $conversation, string $text, bool $failed, ?AssistantReply $reply = null): void
    {
        $conversation->messages()->create([
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $text,
            'failed' => $failed,
            'model' => $reply?->model,
            'input_tokens' => $reply?->inputTokens,
            'output_tokens' => $reply?->outputTokens,
        ]);
    }
}
