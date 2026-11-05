<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_hardening_headers(): void
    {
        $response = $this->get(route('login'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $response->assertHeaderMissing('Strict-Transport-Security'); // plain HTTP

        $this->actingAs($this->userWithRole(User::ROLE_ADMIN))->get(route('dashboard'))->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_is_sent_over_https_when_enabled(): void
    {
        config(['smartcard.security.hsts' => true]);
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_can_be_switched_off(): void
    {
        config(['smartcard.security.csp' => false]);
        $this->get(route('login'))->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_the_app_does_not_leak_framework_details(): void
    {
        $this->get(route('login'))->assertHeaderMissing('X-Powered-By');
        $this->get('/definitely-not-a-page')->assertNotFound()->assertDontSee('Symfony')->assertDontSee('vendor/laravel');
    }
}
