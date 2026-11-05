<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_configured_roles_exist_with_expected_permissions(): void
    {
        foreach (array_keys(config('smartcard.roles')) as $name) {
            $this->assertNotNull(Role::findByName($name, 'web'));
        }

        $this->assertTrue(Role::findByName('sales-manager')->hasPermissionTo('nfc.manage'));
        $this->assertFalse(Role::findByName('designer')->hasPermissionTo('users.manage'));
        $this->assertCount(0, Role::findByName('customer')->permissions);
    }

    public function test_super_admin_passes_every_gate(): void
    {
        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->assertTrue($super->can('roles.manage'));
        $this->assertTrue($super->can('anything-at-all'));
    }

    public function test_only_role_managers_can_view_and_edit_roles(): void
    {
        $admin = $this->userWithRole(User::ROLE_ADMIN); // admin does not have roles.manage by default
        $this->actingAs($admin)->get('/admin/roles')->assertForbidden();

        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->actingAs($super)->get('/admin/roles')->assertOk()->assertSee('Sales Manager');
    }

    public function test_super_admin_can_update_role_permissions(): void
    {
        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $role = Role::findByName('designer');

        $this->actingAs($super)->put("/admin/roles/{$role->id}", ['permissions' => ['dashboard.view', 'customers.view-any']])
            ->assertRedirect('/admin/roles');

        $this->assertTrue($designer->fresh()->can('customers.view-any'));
        $this->assertFalse($designer->fresh()->can('themes.manage'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.updated']);
    }

    public function test_unknown_permissions_are_rejected_and_super_admin_role_is_locked(): void
    {
        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);

        $this->actingAs($super)->put('/admin/roles/'.Role::findByName('designer')->id, ['permissions' => ['hack.everything']])
            ->assertSessionHasErrors('permissions.0');

        $this->actingAs($super)->get('/admin/roles/'.Role::findByName('super-admin')->id.'/edit')->assertForbidden();
    }

    public function test_sidebar_only_shows_permitted_modules(): void
    {
        $designer = $this->userWithRole(User::ROLE_DESIGNER);
        $this->actingAs($designer)->get('/dashboard')->assertOk()->assertDontSee('Roles &amp; permissions', false)->assertDontSee('/admin/users"', false);

        $super = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee('Roles &amp; permissions', false);
    }
}
