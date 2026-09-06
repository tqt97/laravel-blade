<?php

namespace Database\Factories\Cinema;

use App\Models\Cinema\Screening;
use App\Models\Cinema\ScreeningSeat;
use App\Models\Cinema\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreeningSeat>
 */
class ScreeningSeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['screening_id' => Screening::factory(), 'seat_id' => Seat::factory(), 'status' => 'available', 'price_minor_units' => 100000, 'currency' => 'VND'];
    }
}
