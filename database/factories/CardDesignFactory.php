<?php

namespace Database\Factories;

use App\Models\CardDesign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CardDesign> */
class CardDesignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' card',
            'status' => fake()->randomElement(['draft', 'final']),
            'front' => ['background' => ['type' => 'solid', 'color' => '#ffffff'], 'elements' => []],
            'back' => ['background' => ['type' => 'solid', 'color' => '#111827'], 'elements' => []],
        ];
    }
}
