<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Movie;
use App\Models\Catalog\Screening;
use App\Models\Catalog\ScreeningRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screening>
 */
class ScreeningFactory extends Factory
{
    protected $model = Screening::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->startOfHour();

        return ['movie_id' => Movie::factory(), 'screening_room_id' => ScreeningRoom::factory(), 'starts_at' => $start, 'ends_at' => $start->copy()->addHours(2), 'status' => 'scheduled', 'base_price_minor_units' => 100000, 'currency' => 'VND'];
    }
}
