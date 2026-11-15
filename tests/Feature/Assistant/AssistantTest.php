<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\AssistantClient;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeAssistantClient;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private FakeAssistantClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->fake);
    }

    private function ask(User $user, string $message, ?string $conversation = null): TestResponse
    {
        return $this->actingAs($user)->postJson(route('assistant.messages.store'), [
            'message' => $message,
            'conversation' => $conversation,
            'page' => 'Card designs · SmartCard Studio',
        ]);
    }

    public static function roles(): array
    {
        return [['super-admin'], ['admin'], ['designer'], ['sales-manager'], ['customer']];
    }

    #[DataProvider('roles')]
    public function test_every_role_gets_the_page_menu_item_and_widget(string $role): void
    {
        $user = $this->userWithRole($role);

        $items = collect(Navigation::for($user))->flatMap(fn ($s) => $s['items'])->pluck('route');
        $this->assertContains('assistant.index', $items);

        $this->actingAs($user)->get(route('assistant.index'))->assertOk()->assertSee('Your chats');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Open AI assistant');
    }

    public function test_a_question_streams_the_reply_and_saves_the_conversation(): void
    {
        $user = $this->userWithRole('customer');

        $response = $this->ask($user, 'How do I export a PDF?')->assertOk();
        $this->assertSame('Open **Design → Exports** and choose PDF.', $response->streamedContent());

        $conversation = AssistantConversation::sole();
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame('How do I export a PDF?', $conversation->title);
        $response->assertHeader('X-Assistant-Conversation', $conversation->ulid);

        $messages = $conversation->messages()->get();
        $this->assertSame(['user', 'assistant'], $messages->pluck('role')->all());
        $this->assertSame(40, $messages[1]->output_tokens);
        $this->assertFalse($messages[1]->failed);

        // The page the user is on is sent as context, but not stored.
        $sent = $this->fake->requests[0]['messages'];
        $this->assertStringContainsString('Card designs', $sent[0]['content']);
        $this->assertSame('How do I export a PDF?', $messages[0]->content);
    }

    public function test_follow_up_questions_send_the_earlier_turns_and_skip_failed_ones(): void
    {
        $user = $this->userWithRole('admin');
        $this->ask($user, 'First question')->streamedContent();
        $ulid = AssistantConversation::sole()->ulid;

        $this->fake->error = 'The assistant is busy right now.';
        $this->assertStringContainsString('busy', $this->ask($user, 'Second question', $ulid)->streamedContent());
        $this->assertTrue(AssistantMessage::latest('id')->first()->failed);

        $this->fake->error = null;
        $this->ask($user, 'Third question', strtolower($ulid))->streamedContent();

        $sent = collect($this->fake->requests[2]['messages']);
        $this->assertSame(['user', 'assistant', 'user'], $sent->pluck('role')->all());
        $this->assertSame('First question', $sent[0]['content']);
        $this->assertStringEndsWith('Third question', $sent[2]['content']);
        $this->assertSame(1, AssistantConversation::count());
    }

    public function test_the_system_prompt_only_lists_what_the_role_can_open(): void
    {
        $this->ask($this->userWithRole('customer'), 'Hi')->streamedContent();
        $this->ask($this->userWithRole('admin'), 'Hi')->streamedContent();

        [$customer, $admin] = array_column($this->fake->requests, 'system');
        $this->assertStringContainsString('role is Customer', $customer);
        $this->assertStringNotContainsString('- Administration: Users', $customer);
        $this->assertStringContainsString('- Administration: Users', $admin);
        $this->assertStringContainsString('<guide>', $customer);
    }

    public function test_a_refused_request_shows_a_notice_and_is_marked_failed(): void
    {
        $this->fake->reply = '';
        $this->fake->stopReason = 'refusal';

        $content = $this->ask($this->userWithRole('customer'), 'Something odd')->streamedContent();

        $this->assertStringContainsString("can't help with that", $content);
        $this->assertTrue(AssistantMessage::where('role', 'assistant')->sole()->failed);
    }

    public function test_conversations_are_private_to_their_owner(): void
    {
        $owner = $this->userWithRole('customer');
        $this->ask($owner, 'My question')->streamedContent();
        $conversation = AssistantConversation::sole();
        $other = $this->userWithRole('admin');

        $this->actingAs($owner)->getJson(route('assistant.conversations.show', $conversation))
            ->assertOk()->assertJsonPath('messages.0.content', 'My question')->assertJsonMissingPath('messages.0.id');

        $this->actingAs($other)->getJson(route('assistant.conversations.show', $conversation))->assertNotFound();
        $this->actingAs($other)->deleteJson(route('assistant.conversations.destroy', $conversation))->assertNotFound();
        $this->ask($other, 'Sneaky', $conversation->ulid)->assertNotFound();

        $this->actingAs($owner)->deleteJson(route('assistant.conversations.destroy', $conversation))->assertOk();
        $this->assertDatabaseCount('assistant_messages', 0);
    }

    public function test_the_daily_limit_stops_further_questions(): void
    {
        config(['smartcard.assistant.daily_message_limit' => 2]);
        $user = $this->userWithRole('customer');

        $this->ask($user, 'One')->streamedContent();
        $this->ask($user, 'Two')->streamedContent();
        $this->ask($user, 'Three')->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'limit'));

        $this->assertCount(2, $this->fake->requests);
    }

    public function test_without_an_api_key_the_assistant_says_it_is_not_set_up(): void
    {
        $this->fake->configured = false;
        $user = $this->userWithRole('customer');

        $this->actingAs($user)->get(route('assistant.index'))->assertOk()->assertSee('ANTHROPIC_API_KEY');
        $this->ask($user, 'Hello')->assertStatus(503);
        $this->assertDatabaseCount('assistant_conversations', 0);
    }

    public function test_guests_and_users_without_the_permission_are_kept_out(): void
    {
        $this->get(route('assistant.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Open AI assistant');
    }

    public function test_messages_are_validated(): void
    {
        $user = $this->userWithRole('customer');

        $this->ask($user, '')->assertUnprocessable();
        $this->ask($user, str_repeat('a', 4001))->assertUnprocessable();
    }
}
