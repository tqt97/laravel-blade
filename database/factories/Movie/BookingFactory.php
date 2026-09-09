<?php

namespace Database\Factories\Movie;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Screening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Booking $booking): void {
            $booking->setAttribute('status', BookingStatus::Held);
        });
    }

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'screening_id' => Screening::factory(),
            'expires_at' => now()->addMinutes(10),
            'amount_minor_units' => 100000,
            'currency' => 'VND',
            'subtotal_minor_units' => 100000,
            'discount_minor_units' => 0,
            'total_minor_units' => 100000,
            'pricing_currency' => 'VND',
        ];
    }

    public function confirmed(): static
    {
        return $this->afterCreating(function (Booking $booking): void {
            $booking->transitionTo(BookingStatus::Confirmed);
            $booking->saveQuietly();
        });
    }

    public function expired(): static
    {
        return $this->afterCreating(function (Booking $booking): void {
            $booking->transitionTo(BookingStatus::Expired);
            $booking->saveQuietly();
        });
    }
}
