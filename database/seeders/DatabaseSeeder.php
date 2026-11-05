<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles and permissions are always seeded. Demo users and sample data are
     * seeded only outside production (or when SEED_DEMO_DATA=true).
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, ThemeSeeder::class]);

        if (! app()->isProduction() || config('smartcard.seed_demo_data')) {
            $this->call([
                DemoUsersSeeder::class,
                DemoDataSeeder::class,
            ]);
        }
    }
}
