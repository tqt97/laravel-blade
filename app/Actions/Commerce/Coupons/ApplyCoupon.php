<?php

namespace App\Actions\Commerce\Coupons;

use App\Enums\Commerce\CouponReservationStatus;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Models\Booking\Booking;
use App\Models\Commerce\Coupon;
use App\Models\Commerce\CouponReservation;
use App\Models\Commerce\CouponUserUsage;
use App\Support\Booking\BookingMutationGuard;
use Illuminate\Support\Facades\DB;

final class ApplyCoupon
{
    public function __construct(
        private readonly BookingMutationGuard $mutationGuard,
        private readonly CalculateBookingDiscount $discountCalculator,
    ) {}

    public function execute(Booking $booking, string $code): Booking
    {
        return DB::transaction(function () use ($booking, $code): Booking {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->mutationGuard->assertHeldAndBookable($booking, 'booking.messages.coupon_locked');

            $coupon = Coupon::query()
                ->active()
                ->where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if ($coupon === null) {
                throw new BookingOperationFailed(__('booking.messages.coupon_invalid'));
            }

            if (
                $coupon->currency !== null &&
                strtoupper($coupon->currency) !== strtoupper((string) $booking->currency)
            ) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            $hasCurrentReservation = CouponReservation::query()
                ->where('booking_id', $booking->getKey())
                ->where('coupon_id', $coupon->getKey())
                ->where('status', CouponReservationStatus::Reserved)
                ->exists();

            if (! $hasCurrentReservation && $coupon->usage_limit !== null && $coupon->reserved_count + $coupon->redeemed_count >= $coupon->usage_limit) {
                throw new BookingOperationFailed(__('booking.messages.coupon_unavailable'));
            }

            $existing = CouponReservation::query()
                ->where('booking_id', $booking->getKey())
                ->where('status', CouponReservationStatus::Reserved)->lockForUpdate()
                ->first();

            $targetReservation = CouponReservation::query()
                ->where('booking_id', $booking->getKey())
                ->where('coupon_id', $coupon->getKey())
                ->lockForUpdate()
                ->first();

            $userUsage = CouponUserUsage::query()
                ->where('coupon_id', $coupon->getKey())
                ->where('user_id', $booking->getAttribute('user_id'))
                ->lockForUpdate()
                ->first();

            if ($userUsage !== null
                && $userUsage->getRawOriginal('status') !== CouponReservationStatus::Released->value
                && (int) $userUsage->getAttribute('booking_id') !== $booking->getKey()) {
                throw new BookingOperationFailed(__('booking.messages.coupon_user_limit'));
            }

            if ($existing !== null && $existing->coupon_id !== $coupon->getKey()) {
                $existingCoupon = Coupon::query()
                    ->whereKey($existing->coupon_id)
                    ->lockForUpdate()
                    ->first();

                if ($existingCoupon !== null && $existingCoupon->used_count > 0) {
                    $existingCoupon->decrement('used_count');
                    $existingCoupon->decrement('reserved_count');
                }
                $existing->update(['status' => CouponReservationStatus::Released]);
                CouponUserUsage::query()
                    ->where('coupon_id', $existing->coupon_id)
                    ->where('user_id', $booking->getAttribute('user_id'))
                    ->where('booking_id', $booking->getKey())
                    ->where('status', CouponReservationStatus::Reserved)
                    ->update(['status' => CouponReservationStatus::Released]);
            }

            $discount = $this->discountCalculator->execute(
                $booking,
                $coupon,
                (int) $booking->subtotal_minor_units,
            );

            if ($targetReservation === null) {
                CouponReservation::query()->create([
                    'coupon_id' => $coupon->getKey(),
                    'booking_id' => $booking->getKey(),
                    'status' => CouponReservationStatus::Reserved,
                ]);
                $coupon->increment('used_count');
                $coupon->increment('reserved_count');
            } elseif ($targetReservation->getRawOriginal('status') !== CouponReservationStatus::Reserved->value) {
                $targetReservation->update(['status' => CouponReservationStatus::Reserved]);
                $coupon->increment('used_count');
                $coupon->increment('reserved_count');
            }

            if ($userUsage === null) {
                CouponUserUsage::query()->create([
                    'coupon_id' => $coupon->getKey(),
                    'user_id' => $booking->getAttribute('user_id'),
                    'booking_id' => $booking->getKey(),
                    'status' => CouponReservationStatus::Reserved,
                ]);
            } elseif ($userUsage->getRawOriginal('status') === CouponReservationStatus::Released->value) {
                $userUsage->update([
                    'booking_id' => $booking->getKey(),
                    'status' => CouponReservationStatus::Reserved,
                ]);
            }

            $booking->forceFill([
                'coupon_id' => $coupon->getKey(),
                'coupon_code' => (string) $coupon->getAttribute('code'),
                'discount_minor_units' => $discount,
                'total_minor_units' => (int) $booking->subtotal_minor_units - $discount,
                'amount_minor_units' => (int) $booking->subtotal_minor_units - $discount,
            ])->save();

            return $booking->refresh();
        }, 3);
    }
}
