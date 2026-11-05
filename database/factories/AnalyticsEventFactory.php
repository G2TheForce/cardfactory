<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticsEvent> */
class AnalyticsEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_type' => AnalyticsEvent::PROFILE_VIEW,
            'source' => 'direct',
            'device_type' => fake()->randomElement(['mobile', 'mobile', 'desktop', 'tablet']),
            'country_code' => fake()->countryCode(),
            'occurred_at' => now(),
        ];
    }
}
