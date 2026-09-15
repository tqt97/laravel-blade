<?php

namespace App\Actions\Commerce\Coupons;

use App\Enums\Commerce\CouponPricingScope;
use App\Enums\Commerce\CouponType;
use App\Models\Booking\Booking;
use App\Models\Commerce\Coupon;

final class CalculateBookingDiscount
{
    public function execute(Booking $booking, Coupon $coupon, int $subtotalMinorUnits): int
    {
        $scope = CouponPricingScope::tryFrom((string) $coupon->getRawOriginal('pricing_scope'))
            ?? CouponPricingScope::All;

        $discountBase = match ($scope) {
            CouponPricingScope::All => $subtotalMinorUnits,
            CouponPricingScope::TicketsOnly => (int) $booking->items()->sum('price_minor_units'),
            CouponPricingScope::ConcessionsOnly => (int) $booking->concessions()->sum('total_minor_units'),
        };

        $couponValue = (int) $coupon->getAttribute('value');
        $discount = CouponType::from((string) $coupon->getRawOriginal('type')) === CouponType::Percentage
            ? intdiv($discountBase * min(100, $couponValue), 100)
            : $couponValue;

        if ($coupon->maximum_discount_minor_units !== null) {
            $discount = min($discount, (int) $coupon->maximum_discount_minor_units);
        }

        return min($discount, $discountBase);
    }
}
