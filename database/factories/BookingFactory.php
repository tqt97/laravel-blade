<?php

namespace Database\Factories;

use App\Booking\Enums\BookingStatus;
use App\Booking\Models\Booking;
use App\Models\BookableResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $start = now()->addDay()->startOfHour();

        return [
            'user_id' => User::factory(),
            'resource_id' => BookableResource::factory(),
            'status' => BookingStatus::Held,
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
