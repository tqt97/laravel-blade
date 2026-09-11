<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Booking\CouponPricingScope;
use App\Enums\Movie\Booking\CouponReservationStatus;
use App\Enums\Movie\Booking\CouponType;
use App\Models\Movie\Booking;
use App\Models\Movie\Coupon;
use App\Models\Movie\CouponReservation;
use App\Models\Movie\CouponUserUsage;
use App\Support\Booking\BookingMutationGuard;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;

final class ApplyCoupon
{
    public function __construct(private readonly BookingMutationGuard $mutationGuard) {}

    public function execute(Booking $booking, string $code): Booking
    {
        return DB::transaction(function () use ($booking, $code): Booking {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->mutationGuard->assertHeldAndBookable($booking, 'booking.messages.coupon_locked');

            $coupon = Coupon::query()
                ->where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            $startsAt = $coupon?->getRawOriginal('starts_at');
            $endsAt = $coupon?->getRawOriginal('ends_at');

            if (
                $coupon === null ||
                ! (bool) $coupon->getAttribute('is_active') ||
                ($startsAt !== null &&
                    BookingClock::parseStored((string) $startsAt)?->isFuture()) ||
                ($endsAt !== null &&
                    BookingClock::parseStored((string) $endsAt)?->isPast())
            ) {
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

            $scope = CouponPricingScope::tryFrom((string) $coupon->getRawOriginal('pricing_scope')) ?? CouponPricingScope::All;
            $gross = $this->grossForScope($booking, $scope);
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

    private function grossForScope(Booking $booking, CouponPricingScope $scope): int
    {
        return match ($scope) {
            CouponPricingScope::All => (int) $booking->subtotal_minor_units,
            CouponPricingScope::TicketsOnly => (int) $booking->items()->sum('price_minor_units'),
            CouponPricingScope::ConcessionsOnly => (int) $booking->concessions()->sum('total_minor_units'),
        };
    }
}
