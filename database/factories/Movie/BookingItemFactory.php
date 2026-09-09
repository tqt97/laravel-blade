<?php

namespace Database\Factories\Movie;

use App\Models\Movie\Booking;
use App\Models\Movie\BookingItem;
use App\Models\Movie\ScreeningSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingItem>
 */
class BookingItemFactory extends Factory
{
    protected $model = BookingItem::class;

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
