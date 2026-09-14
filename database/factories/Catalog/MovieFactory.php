<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Movie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movie>
 */
class MovieFactory extends Factory
{
    protected $model = Movie::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['title' => fake()->unique()->sentence(3), 'synopsis' => fake()->paragraph(), 'duration_minutes' => 120, 'rating' => 'PG-13', 'is_active' => true];
    }
}
