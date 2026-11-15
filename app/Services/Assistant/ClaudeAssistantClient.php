<?php

namespace App\Services\Assistant;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\OverloadedException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Generator;
use Illuminate\Support\Facades\Log;

/** Streams replies from the Claude API with the official Anthropic PHP SDK. */
class ClaudeAssistantClient implements AssistantClient
{
    /** Models that accept server-side refusal fallbacks ("fallbacks": "default"). */
    private const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5', 'claude-fable-5-2', 'claude-fable-5-1'];

    private ?Client $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public function stream(string $system, array $messages): Generator
    {
        $model = (string) config('services.anthropic.model');
        $text = '';
        $stopReason = null;
        $inputTokens = $outputTokens = null;

        // A declined request is re-run on Anthropic's recommended fallback model instead of failing.
        $fallbacks = in_array($model, self::FALLBACK_MODELS, true);

        try {
            $stream = $this->client()->beta->messages->createStream(
                maxTokens: (int) config('services.anthropic.max_tokens', 8000),
                messages: $messages,
                model: $model,
                // The system prompt is the same for every message from this user, so cache it.
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: ['effort' => (string) config('services.anthropic.effort', 'low')],
                fallbacks: $fallbacks ? 'default' : null,
                betas: $fallbacks ? ['server-side-fallback-2026-07-01'] : null,
            );

            foreach ($stream as $event) {
                if ($event instanceof BetaRawMessageStartEvent) {
                    $inputTokens = $event->message->usage->inputTokens;
                    $model = $event->message->model;
                } elseif ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                    $text .= $event->delta->text;
                    yield $event->delta->text;
                } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                    $outputTokens = $event->usage->outputTokens;
                }
            }
        } catch (AuthenticationException|PermissionDeniedException $e) {
            $this->report($e);
            throw new AssistantException('The assistant\'s API key was refused. Ask an administrator to check ANTHROPIC_API_KEY.', previous: $e);
        } catch (RateLimitException|OverloadedException $e) {
            throw new AssistantException('The assistant is busy right now. Please try again in a minute.', previous: $e);
        } catch (APIConnectionException $e) {
            $this->report($e);
            throw new AssistantException('Could not reach the assistant. Check the server\'s internet connection and try again.', previous: $e);
        } catch (APIException $e) {
            $this->report($e);
            throw new AssistantException('The assistant could not answer that just now. Please try again.', previous: $e);
        }

        return new AssistantReply($text, (string) $model, $stopReason, $inputTokens, $outputTokens);
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: (string) config('services.anthropic.key'));
    }

    private function report(APIException $e): void
    {
        Log::warning('Assistant request failed: '.$e->getMessage(), ['exception' => $e::class]);
    }
}
