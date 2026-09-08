<?php

namespace Database\Factories\Cinema;

use App\Models\Cinema\Concession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Concession>
 */
class ConcessionFactory extends Factory
{
    protected $model = Concession::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sku' => strtoupper(fake()->unique()->bothify('COMBO-###')),
            'price_minor_units' => fake()->randomElement([25000, 55000, 65000, 120000]),
            'currency' => 'VND',
            'stock' => 100,
            'is_active' => true,
        ];
    }
}
