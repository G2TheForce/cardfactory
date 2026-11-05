<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin(): void
    {
        $this->artisan('smartcard:create-admin', ['email' => 'Owner@Example.com', '--name' => 'Owner'])
            ->expectsQuestion('Password', 'a-Strong-pass-123')
            ->expectsQuestion('Repeat password', 'a-Strong-pass-123')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(User::ROLE_SUPER_ADMIN));
        $this->assertTrue(Hash::check('a-Strong-pass-123', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_rejects_mismatched_passwords_and_promotes_existing_users(): void
    {
        $this->artisan('smartcard:create-admin', ['email' => 'x@example.com', '--name' => 'X'])
            ->expectsQuestion('Password', 'one-password-1')
            ->expectsQuestion('Repeat password', 'another-pass-2')
            ->assertFailed();
        $this->assertSame(0, User::where('email', 'x@example.com')->count());

        $user = $this->userWithRole(User::ROLE_DESIGNER, ['email' => 'dana@example.com']);
        $this->artisan('smartcard:create-admin', ['email' => 'dana@example.com'])
            ->expectsConfirmation("{$user->name} <dana@example.com> already exists. Make them Super Admin?", 'yes')
            ->assertSuccessful();
        $this->assertSame([User::ROLE_SUPER_ADMIN], $user->fresh()->getRoleNames()->all());
    }
}
