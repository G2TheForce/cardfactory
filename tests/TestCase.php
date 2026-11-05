<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Seed roles before each test that uses RefreshDatabase. */
    protected bool $seedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedRoles && in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}
