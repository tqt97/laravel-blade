<?php

namespace Database\Factories\Cinema;

use App\Models\Cinema\ScreeningRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreeningRoom>
 */
class ScreeningRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => 'Room '.fake()->unique()->numberBetween(1, 999), 'code' => fake()->unique()->bothify('R##'), 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => true];
    }
}
