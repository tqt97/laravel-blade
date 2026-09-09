<?php

namespace Database\Factories\Movie;

use App\Enums\Movie\Booking\CouponReservationStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Coupon;
use App\Models\Movie\CouponReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponReservation>
 */
class CouponReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => fn (): int => Coupon::factory()->create()->id,
            'booking_id' => fn (): int => Booking::factory()->create()->id,
            'status' => CouponReservationStatus::Reserved,
        ];
    }
}
