<?php

namespace Database\Factories\Cinema;

use App\Models\Cinema\ScreeningRoom;
use App\Models\Cinema\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['screening_room_id' => ScreeningRoom::factory(), 'row_label' => fake()->randomLetter(), 'seat_number' => fake()->numberBetween(1, 20), 'seat_type' => 'regular', 'price_minor_units' => 100000, 'is_active' => true];
    }
}
