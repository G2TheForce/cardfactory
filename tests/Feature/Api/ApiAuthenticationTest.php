<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_token_with_read_ability_returns_profile_without_internal_id(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $token = $user->createToken('test', ['read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid)
            ->assertJsonPath('data.roles.0', 'designer')
            ->assertJsonMissingPath('data.password');
    }

    public function test_token_without_read_ability_is_forbidden(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $token = $user->createToken('test', ['write'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_tokens_of_inactive_users_are_rejected(): void
    {
        $user = $this->userWithRole(User::ROLE_DESIGNER);
        $token = $user->createToken('test', ['read'])->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_dashboard_endpoint_scopes_by_role(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $this->withToken($designer->createToken('t', ['read'])->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('data.scope', 'platform')->assertJsonMissingPath('data.stats.revenue');

        $this->app['auth']->forgetGuards();

        $customerUser = $this->userWithRole(User::ROLE_CUSTOMER);
        Customer::factory()->create(['user_id' => $customerUser->id]);
        $this->withToken($customerUser->createToken('t', ['read'])->plainTextToken)
            ->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('data.scope', 'customer')->assertJsonStructure(['data' => ['stats' => ['views', 'scans']]]);
    }
}
