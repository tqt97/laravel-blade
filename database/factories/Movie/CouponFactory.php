<?php

namespace Database\Factories\Movie;

use App\Enums\Movie\Booking\CouponType;
use App\Models\Movie\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('MOVIE-????'),
            'type' => CouponType::Fixed,
            'value' => 25000,
            'maximum_discount_minor_units' => null,
            'currency' => 'VND',
            'usage_limit' => 100,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
