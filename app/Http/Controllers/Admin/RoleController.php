<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->withCount('users')->get()
            ->sortBy(fn (Role $role) => array_search($role->name, array_keys(config('smartcard.roles'))))
            ->values();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissions' => config('smartcard.permissions'),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->name === User::ROLE_SUPER_ADMIN, 403, 'Super Admin always has every permission.');

        return view('admin.roles.edit', [
            'role' => $role->load('permissions:id,name'),
            'permissions' => config('smartcard.permissions'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === User::ROLE_SUPER_ADMIN, 403, 'Super Admin always has every permission.');

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('smartcard.permissions')))],
        ]);

        $before = $role->permissions->pluck('name')->all();
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->logger->log('role.updated', $role, "Updated permissions for {$role->name}", [
            'added' => array_values(array_diff($data['permissions'] ?? [], $before)),
            'removed' => array_values(array_diff($before, $data['permissions'] ?? [])),
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Permissions updated for '.config("smartcard.roles.{$role->name}.label", $role->name).'.');
    }
}
