<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DigitalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DigitalProfile> */
class DigitalProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'slug' => Str::slug(fake()->unique()->userName()).'-'.Str::lower(Str::random(5)),
            'profile_theme' => 'classic',
            'is_active' => true,
            'published_at' => now(),
        ];
    }
}
