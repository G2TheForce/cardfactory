<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_information_and_avatar_can_be_updated(): void
    {
        Storage::fake('public');
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->from('/settings/profile')->put('/user/profile-information', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+1 555 0100',
            'avatar' => UploadedFile::fake()->image('me.png', 200, 200),
        ])->assertRedirect('/settings/profile');

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_avatar_must_be_an_image(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => 'X', 'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrorsIn('updateProfileInformation', 'avatar');
    }

    public function test_password_can_be_changed(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'new-secret-pass',
            'password_confirmation' => 'new-secret-pass',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-secret-pass', $user->fresh()->password));
    }

    public function test_api_tokens_can_be_created_and_revoked_only_by_owner(): void
    {
        $user = $this->userWithRole(User::ROLE_CUSTOMER);
        $other = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($user)->post('/settings/api-tokens', ['name' => 'CRM', 'abilities' => ['read'], 'expires_in_days' => 30])
            ->assertRedirect('/settings/api-tokens')->assertSessionHas('plainTextToken');

        $token = $user->tokens()->firstOrFail();
        $this->assertSame(['read'], $token->abilities);
        $this->assertNotNull($token->expires_at);

        $this->actingAs($other)->delete("/settings/api-tokens/{$token->id}")->assertNotFound();
        $this->actingAs($user)->delete("/settings/api-tokens/{$token->id}")->assertRedirect();
        $this->assertSame(0, $user->tokens()->count());
    }
}
