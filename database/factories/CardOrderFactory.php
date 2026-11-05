<?php

namespace Database\Factories;

use App\Models\CardOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CardOrder> */
class CardOrderFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 50);
        $unit = fake()->randomElement([9.0, 14.0, 29.0]);
        $subtotal = $qty * $unit;

        return [
            'order_number' => 'SO-'.Str::upper(Str::random(8)),
            'status' => 'paid',
            'items' => [['sku' => 'NFC-PVC-215', 'name' => 'PVC NFC card', 'qty' => $qty, 'unit_price' => $unit]],
            'quantity' => $qty,
            'subtotal' => $subtotal,
            'tax' => round($subtotal * 0.1, 2),
            'total' => round($subtotal * 1.1, 2),
            'currency' => 'USD',
            'paid_at' => now(),
        ];
    }
}
