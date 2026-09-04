<?php

namespace Database\Factories;

use App\Models\BookableResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookableResource> */
class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Room';

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ];
    }
}
