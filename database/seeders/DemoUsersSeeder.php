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
                    'department' => 'Brand & Growth',
                    'mobile' => '+15550100200',
                    'whatsapp' => '+15550100200',
                    'office_phone' => '+1 555 010 7700',
                    'website' => 'https://example.com',
                    'office_address' => '120 Market Street, Suite 400',
                    'city' => 'San Francisco',
                    'province' => 'CA',
                    'country' => 'United States',
                    'latitude' => 37.7936,
                    'longitude' => -122.3965,
                    'introduction' => 'I lead brand and growth marketing, helping B2B teams turn great products into stories people remember. Happy to talk partnerships, events and speaking.',
                    'social_links' => ['linkedin' => 'https://linkedin.com/in/example', 'instagram' => 'https://instagram.com/example', 'x' => 'https://x.com/example'],
                    'business' => [
                        'description' => null,
                        'services' => ['Brand strategy', 'Campaigns', 'Events', 'Public speaking'],
                        'hours' => ['monday' => '9:00 – 17:30', 'tuesday' => '9:00 – 17:30', 'wednesday' => '9:00 – 17:30', 'thursday' => '9:00 – 17:30', 'friday' => '9:00 – 15:00'],
                    ],
                    'booking_url' => 'https://example.com/book',
                    'custom_links' => [['label' => 'Latest case study', 'url' => 'https://example.com/case-study']],
                    'status' => 'active',
                ]);
            }
        }
    }
}
