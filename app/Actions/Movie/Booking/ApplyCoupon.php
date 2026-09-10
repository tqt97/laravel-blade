<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Booking\CouponReservationStatus;
use App\Enums\Movie\Booking\CouponType;
use App\Models\Movie\Booking;
use App\Models\Movie\Coupon;
use App\Models\Movie\CouponReservation;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;

final class ApplyCoupon
{
    public function execute(Booking $booking, string $code): Booking
    {
        return DB::transaction(function () use ($booking, $code): Booking {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($booking->getRawOriginal('status') !== BookingStatus::Held->value) {
                throw new BookingOperationFailed(__('booking.messages.coupon_locked'));
            }

            $coupon = Coupon::query()->whereRaw('upper(code) = ?', [strtoupper(trim($code))])->lockForUpdate()->first();
            $startsAt = $coupon?->getRawOriginal('starts_at');
            $endsAt = $coupon?->getRawOriginal('ends_at');

            if ($coupon === null || ! (bool) $coupon->getAttribute('is_active') || ($startsAt !== null && BookingClock::parseStored((string) $startsAt)?->isFuture()) || ($endsAt !== null && BookingClock::parseStored((string) $endsAt)?->isPast())) {
                throw new BookingOperationFailed(__('booking.messages.coupon_invalid'));
            }

            if ($coupon->currency !== null && strtoupper($coupon->currency) !== strtoupper((string) $booking->currency)) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                throw new BookingOperationFailed(__('booking.messages.coupon_unavailable'));
            }

            $existing = CouponReservation::query()->where('booking_id', $booking->getKey())->where('status', CouponReservationStatus::Reserved)->lockForUpdate()->first();
            $targetReservation = CouponReservation::query()
                ->where('booking_id', $booking->getKey())
                ->where('coupon_id', $coupon->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->coupon_id !== $coupon->getKey()) {
                $existingCoupon = Coupon::query()->whereKey($existing->coupon_id)->lockForUpdate()->first();
                if ($existingCoupon !== null && $existingCoupon->used_count > 0) {
                    $existingCoupon->decrement('used_count');
                }
                $existing->update(['status' => CouponReservationStatus::Released]);
            }

            $gross = (int) $booking->subtotal_minor_units;
            $couponType = CouponType::from((string) $coupon->getRawOriginal('type'));
            $couponValue = (int) $coupon->getAttribute('value');
            $discount = $couponType === CouponType::Percentage
                ? intdiv($gross * min(100, $couponValue), 100)
                : $couponValue;

            if ($coupon->maximum_discount_minor_units !== null) {
                $discount = min($discount, $coupon->maximum_discount_minor_units);
            }

            $discount = min($discount, $gross);

            if ($targetReservation === null) {
                CouponReservation::query()->create([
                    'coupon_id' => $coupon->getKey(),
                    'booking_id' => $booking->getKey(),
                    'status' => CouponReservationStatus::Reserved,
                ]);
                $coupon->increment('used_count');
            } elseif ($targetReservation->getRawOriginal('status') !== CouponReservationStatus::Reserved->value) {
                $targetReservation->update(['status' => CouponReservationStatus::Reserved]);
                $coupon->increment('used_count');
            }

            $booking->forceFill([
                'coupon_id' => $coupon->getKey(),
                'coupon_code' => (string) $coupon->getAttribute('code'),
                'discount_minor_units' => $discount,
                'total_minor_units' => $gross - $discount,
                'amount_minor_units' => $gross - $discount,
            ])->save();

            return $booking->refresh();
        }, 3);
    }
}
