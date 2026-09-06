<?php

namespace Database\Factories\Cinema;

use App\Models\Cinema\Booking;
use App\Models\Cinema\BookingItem;
use App\Models\Cinema\ScreeningSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'screening_seat_id' => ScreeningSeat::factory(), 'ticket_code' => strtoupper(fake()->unique()->bothify('TKT-########')), 'price_minor_units' => 100000, 'currency' => 'VND', 'status' => 'issued'];
    }
}
