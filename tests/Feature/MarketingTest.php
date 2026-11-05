<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_all_sections(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="features"', false)->assertSee('id="themes"', false)->assertSee('id="how"', false)
            ->assertSee('id="pricing"', false)->assertSee('id="faq"', false)->assertSee('id="contact"', false);
    }

    public function test_legal_pages(): void
    {
        $this->get('/legal/terms')->assertOk();
        $this->get('/legal/privacy')->assertOk();
        $this->get('/legal/other')->assertNotFound();
    }

    public function test_contact_form_sends_mail(): void
    {
        Mail::fake();

        $this->post('/contact', ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'We need 200 NFC cards.'])
            ->assertRedirect()->assertSessionHas('contact_sent');

        Mail::assertQueued(ContactMessage::class, fn ($mail) => $mail->data['email'] === 'ann@example.com');
    }

    public function test_contact_form_validates_and_blocks_honeypot(): void
    {
        Mail::fake();

        $this->post('/contact', ['name' => '', 'email' => 'bad', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap stuff now!!', 'website' => 'spam'])
            ->assertSessionHasErrors('website');

        Mail::assertNothingQueued();
    }
}
