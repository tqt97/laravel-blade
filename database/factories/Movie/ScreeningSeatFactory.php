<?php

namespace Database\Factories\Movie;

use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningSeat;
use App\Models\Movie\Seat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreeningSeat>
 */
class ScreeningSeatFactory extends Factory
{
    protected $model = ScreeningSeat::class;

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
