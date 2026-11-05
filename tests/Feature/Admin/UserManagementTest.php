<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public static function forbiddenRoles(): array
    {
        return [[User::ROLE_CUSTOMER], [User::ROLE_DESIGNER], [User::ROLE_SALES_MANAGER]];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_non_admin_roles_cannot_manage_users(string $role): void
    {
        $actor = $this->userWithRole($role);
        $other = $this->userWithRole(User::ROLE_CUSTOMER);

        $this->actingAs($actor)->get('/admin/users')->assertForbidden();
        $this->actingAs($actor)->post('/admin/users', [])->assertForbidden();
        $this->actingAs($actor)->put("/admin/users/{$other->ulid}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($actor)->delete("/admin/users/{$other->ulid}")->assertForbidden();
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $this->userWithRole(User::ROLE_DESIGNER, ['name' => 'Dora Designer']);
        $this->userWithRole(User::ROLE_CUSTOMER, ['name' => 'Carl Customer']);

        $this->actingAs($admin)->get('/admin/users?role=designer')
            ->assertOk()->assertSee('Dora Designer')->assertDontSee('Carl Customer');
    }

    public function test_admin_can_create_a_customer_user_with_customer_record(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'role' => User::ROLE_CUSTOMER,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'is_active' => '1',
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole(User::ROLE_CUSTOMER));
        $this->assertDatabaseHas('customers', ['user_id' => $user->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_admin_cannot_grant_super_admin(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => User::ROLE_SUPER_ADMIN,
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_admin_cannot_edit_or_delete_a_super_admin(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)->get("/admin/users/{$super->ulid}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/admin/users/{$super->ulid}")->assertForbidden();
    }

    public function test_admin_can_update_role_and_deactivate_user_which_revokes_tokens(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $target = $this->userWithRole(User::ROLE_CUSTOMER);
        $target->createToken('t', ['read']);

        $this->actingAs($admin)->put("/admin/users/{$target->ulid}", [
            'name' => 'Renamed', 'email' => $target->email, 'role' => User::ROLE_DESIGNER, 'is_active' => '0',
        ])->assertRedirect('/admin/users');

        $target->refresh();
        $this->assertSame('Renamed', $target->name);
        $this->assertTrue($target->hasRole(User::ROLE_DESIGNER));
        $this->assertFalse($target->hasRole(User::ROLE_CUSTOMER));
        $this->assertFalse($target->is_active);
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_users_cannot_demote_or_deactivate_themselves(): void
    {
        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);

        $this->actingAs($super)->put("/admin/users/{$super->ulid}", [
            'name' => $super->name, 'email' => $super->email, 'role' => User::ROLE_ADMIN, 'is_active' => '0',
        ])->assertSessionHasErrors(['role', 'is_active']);

        $this->actingAs($super)->delete("/admin/users/{$super->ulid}")->assertForbidden();
        $this->assertNotSoftDeleted($super);
    }

    public function test_admin_can_soft_delete_a_user(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $target = $this->userWithRole(User::ROLE_DESIGNER);

        $this->actingAs($admin)->delete("/admin/users/{$target->ulid}")->assertRedirect('/admin/users');
        $this->assertSoftDeleted($target);
    }

    public function test_users_are_addressed_by_ulid_not_sequential_id(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN);
        $target = $this->userWithRole(User::ROLE_DESIGNER);

        $this->actingAs($admin)->get("/admin/users/{$target->id}/edit")->assertNotFound();
        $this->actingAs($admin)->get("/admin/users/{$target->ulid}/edit")->assertOk();
    }
}
