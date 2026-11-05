<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        $handle = strtolower(preg_replace('/[^a-z]/i', '', $name));

        return [
            'full_name' => $name,
            'professional_title' => fake()->jobTitle(),
            'designation' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Sales', 'Marketing', 'Engineering', 'Operations', 'Finance', null]),
            'mobile' => fake()->e164PhoneNumber(),
            'whatsapp' => fake()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'website' => 'https://'.fake()->domainName(),
            'office_phone' => fake()->phoneNumber(),
            'country' => fake()->country(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'office_address' => fake()->streetAddress(),
            'social_links' => [
                'linkedin' => "https://linkedin.com/in/$handle",
                'instagram' => "https://instagram.com/$handle",
            ],
            'business' => [
                'description' => fake()->catchPhrase(),
                'services' => fake()->words(3),
            ],
            'introduction' => fake()->sentence(16),
            'status' => 'active',
        ];
    }
}
