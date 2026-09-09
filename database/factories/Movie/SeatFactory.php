<?php

namespace Database\Factories\Movie;

use App\Models\Movie\ScreeningRoom;
use App\Models\Movie\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seat>
 */
class SeatFactory extends Factory
{
    protected $model = Seat::class;

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
