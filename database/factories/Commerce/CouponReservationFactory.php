<?php

namespace Database\Factories\Commerce;

use App\Enums\Commerce\CouponReservationStatus;
use App\Models\Booking\Booking;
use App\Models\Commerce\Coupon;
use App\Models\Commerce\CouponReservation;
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
