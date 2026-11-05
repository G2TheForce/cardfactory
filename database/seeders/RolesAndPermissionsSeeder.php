<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: syncs config/smartcard.php roles and permissions into the database.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = array_keys(config('smartcard.permissions'));

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('smartcard.roles') as $name => $definition) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($definition['permissions'] === ['*'] ? $all : $definition['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
