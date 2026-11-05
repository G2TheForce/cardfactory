<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for LOCAL DEVELOPMENT ONLY. Password for all: "password".
 */
class DemoUsersSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['Super Admin', 'superadmin@smartcard.test', User::ROLE_SUPER_ADMIN],
        ['Avery Admin', 'admin@smartcard.test', User::ROLE_ADMIN],
        ['Dana Designer', 'designer@smartcard.test', User::ROLE_DESIGNER],
        ['Sam Sales', 'sales@smartcard.test', User::ROLE_SALES_MANAGER],
        ['Casey Customer', 'customer@smartcard.test', User::ROLE_CUSTOMER],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);

            if ($role === User::ROLE_CUSTOMER) {
                Customer::firstOrCreate(['user_id' => $user->id], [
                    'full_name' => $name,
                    'email' => $email,
                    'professional_title' => 'Marketing Director',
                    'designation' => 'Marketing Director',
                    'mobile' => '+15550100200',
                ]);
            }
        }
    }
}
