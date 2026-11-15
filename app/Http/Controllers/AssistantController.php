<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('assistant.index', [
            'configured' => $this->assistant->isConfigured(),
            'remaining' => $this->assistant->remainingToday($user),
            'conversations' => AssistantConversation::query()->where('user_id', $user->id)
                ->latest('updated_at')->limit(50)->get(['id', 'ulid', 'title', 'updated_at'])
                ->map(fn (AssistantConversation $c) => $this->summary($c)),
        ]);
    }

    public function show(Request $request, AssistantConversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);

        return response()->json([
            ...$this->summary($conversation),
            'messages' => $conversation->messages()->get(['role', 'content', 'failed'])
                ->map(fn (AssistantMessage $m) => ['role' => $m->role, 'content' => $m->content, 'failed' => $m->failed]),
        ]);
    }

    /** Sends a question and streams the reply back as plain text. */
    public function store(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.config('smartcard.assistant.max_message_length', 4000)],
            'conversation' => ['nullable', 'string', 'size:26'],
            'page' => ['nullable', 'string', 'max:200'],
        ]);
        $user = $request->user();

        if (! $this->assistant->isConfigured()) {
            return response()->json(['message' => 'The assistant is not set up yet. An administrator needs to add ANTHROPIC_API_KEY to the .env file.'], 503);
        }
        if ($this->assistant->remainingToday($user) === 0) {
            return response()->json(['message' => 'You\'ve reached today\'s message limit for the assistant. It resets at midnight.'], 429);
        }

        $question = trim($data['message']);
        $conversation = filled($data['conversation'] ?? null)
            ? AssistantConversation::query()->where('user_id', $user->id)->where('ulid', Str::upper($data['conversation']))->firstOrFail()
            : $this->assistant->startConversation($user, $question);

        $reply = $this->assistant->reply($conversation, $question, $data['page'] ?? null);

        return response()->stream(function () use ($reply) {
            foreach ($reply as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            // Stop nginx buffering the stream, so text appears as it's written.
            'X-Accel-Buffering' => 'no',
            'X-Assistant-Conversation' => $conversation->ulid,
            'X-Assistant-Title' => rawurlencode($conversation->title),
        ]);
    }

    public function destroy(Request $request, AssistantConversation $conversation): JsonResponse
    {
        $this->authorizeOwner($request, $conversation);
        $conversation->delete();

        return response()->json(['deleted' => true]);
    }

    /** Conversations are private: anyone else gets a 404, not a hint that it exists. */
    private function authorizeOwner(Request $request, AssistantConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }

    /** @return array{ulid: string, title: string, updated: string} */
    private function summary(AssistantConversation $conversation): array
    {
        return ['ulid' => $conversation->ulid, 'title' => $conversation->title, 'updated' => $conversation->updated_at->diffForHumans()];
    }
}
