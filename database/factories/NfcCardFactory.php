<?php

namespace Database\Factories;

use App\Models\NfcCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NfcCard> */
class NfcCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'card_identifier' => 'SC-'.Str::upper(Str::random(8)),
            'uid' => strtoupper(implode(':', str_split(bin2hex(random_bytes(7)), 2))),
            'tag_type' => fake()->randomElement(['NTAG213', 'NTAG215', 'NTAG216']),
            'status' => 'in_stock',
            'batch_number' => 'B'.fake()->numerify('####'),
        ];
    }
}
