<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_customer_account_and_record(): void
    {
        $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(User::ROLE_CUSTOMER));
        $this->assertNotNull($user->ulid);
        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'full_name' => 'Jane Doe']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_terms_and_unique_email(): void
    {
        $this->userWithRole(User::ROLE_CUSTOMER, ['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'X', 'email' => 'taken@example.com',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertSessionHasErrors(['email', 'terms']);
    }
}
