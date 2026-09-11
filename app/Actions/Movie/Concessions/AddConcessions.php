<?php

namespace App\Actions\Movie\Concessions;

use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Enums\Movie\Booking\CouponPricingScope;
use App\Enums\Movie\Booking\CouponType;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\Coupon;
use App\Support\Booking\BookingMutationGuard;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;

final class AddConcessions
{
    public function __construct(private readonly BookingMutationGuard $mutationGuard) {}

    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, array $quantitiesByConcession): Booking
    {
        ksort($quantitiesByConcession);

        return DB::transaction(function () use ($booking, $quantitiesByConcession): Booking {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $this->executeForLockedBooking($booking, $quantitiesByConcession);
        }, 3);
    }

    /**
     * Sync combo quantities while the caller owns the booking row lock.
     *
     * This is used by the payment transaction so stock validation, booking
     * totals, and the payment claim commit or roll back together.
     *
     * @param  array<int, int>  $quantitiesByConcession
     */
    public function executeForLockedBooking(Booking $booking, array $quantitiesByConcession): Booking
    {
        ksort($quantitiesByConcession);

        $this->mutationGuard->assertHeldAndBookable($booking);

        $ticketCount = $booking->items()->count();
        $maxComboCount = $ticketCount * (int) config('booking.limits.max_combos_per_ticket');
        $existingLines = $booking->concessions()->lockForUpdate()->get()->keyBy('concession_id');

        $concessionIds = $existingLines->keys()
            ->merge(array_keys($quantitiesByConcession))
            ->map(static fn (int|string $concessionId): int => (int) $concessionId)
            ->unique()
            ->sort()
            ->values();

        $concessions = Concession::query()
            ->whereIn('id', $concessionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $requestedComboCount = $concessionIds->sum(
            function (int $concessionId) use ($quantitiesByConcession): int {
                $quantity = (int) ($quantitiesByConcession[$concessionId] ?? 0);

                if (
                    $quantity < 0
                    || $quantity > (int) config('booking.limits.max_combo_quantity')
                ) {
                    throw new BookingOperationFailed(__('booking.messages.combo_limit_per_seat'));
                }

                return $quantity;
            }
        );

        if ($requestedComboCount > $maxComboCount) {
            throw new BookingOperationFailed(__('booking.messages.combo_limit_per_seat'));
        }

        $totalDelta = 0;
        foreach ($concessionIds as $concessionId) {
            $desiredQuantity = (int) ($quantitiesByConcession[$concessionId] ?? 0);
            $concession = $concessions->get($concessionId);
            $line = $existingLines->get($concessionId);

            if ($desiredQuantity > 0 && ($concession === null || ! $concession->is_active)) {
                throw new BookingOperationFailed(__('booking.messages.combo_unavailable'));
            }

            if ($concession === null) {
                continue;
            }

            if (strtoupper((string) $booking->currency) !== strtoupper((string) $concession->currency)) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            if ($line !== null && strtoupper((string) $line->currency) !== strtoupper((string) $booking->currency)) {
                throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
            }

            $currentQuantity = (int) ($line?->getAttribute('quantity') ?? 0);
            $currentTotal = (int) ($line?->getAttribute('total_minor_units') ?? 0);
            $unit = (int) ($line?->getAttribute('unit_price_minor_units') ?? $concession->getAttribute('price_minor_units'));
            $quantityDelta = $desiredQuantity - $currentQuantity;

            if ($quantityDelta === 0) {
                continue;
            }

            $stock = $concession->getAttribute('stock');
            if ($quantityDelta > 0 && $stock !== null && $stock < $quantityDelta) {
                throw new BookingOperationFailed(__('booking.messages.combo_stock_unavailable'));
            }

            $newTotal = $unit * $desiredQuantity;
            if ($desiredQuantity === 0) {
                $line?->delete();
            } elseif ($line === null) {
                $booking->concessions()->create([
                    'concession_id' => $concession->getKey(),
                    'quantity' => $desiredQuantity,
                    'unit_price_minor_units' => $unit,
                    'total_minor_units' => $newTotal,
                    'currency' => strtoupper((string) $concession->getAttribute('currency')),
                ]);
            } else {
                $line->forceFill([
                    'quantity' => $desiredQuantity,
                    'total_minor_units' => $newTotal,
                ])->save();
            }

            $stockBefore = $stock;
            if ($stock !== null && $quantityDelta > 0) {
                $concession->decrement('stock', $quantityDelta);
            } elseif ($stock !== null) {
                $concession->increment('stock', -$quantityDelta);
            }

            InventoryMovement::query()->create([
                'concession_id' => $concession->getKey(),
                'booking_id' => $booking->getKey(),
                'type' => $quantityDelta > 0 ? InventoryMovementType::Reserve : InventoryMovementType::Release,
                'stock_mode' => $stock === null ? InventoryStockMode::Unlimited : InventoryStockMode::Finite,
                'quantity_delta' => -$quantityDelta,
                'stock_before' => $stockBefore,
                'stock_after' => $stock === null ? null : (int) $concession->fresh()->stock,
                'reference' => 'booking-'.$booking->getKey(),
                'idempotency_key' => sprintf(
                    'booking:%d:concession:%d:from:%d:to:%d',
                    $booking->getKey(),
                    $concession->getKey(),
                    $currentQuantity,
                    $desiredQuantity,
                ),
            ]);

            $totalDelta += $newTotal - $currentTotal;
        }
        $newSubtotal = (int) $booking->getAttribute('subtotal_minor_units') + $totalDelta;
        $discount = (int) $booking->getAttribute('discount_minor_units');

        if ($booking->coupon_id !== null) {
            $coupon = Coupon::query()->whereKey($booking->coupon_id)->first();
            if ($coupon !== null) {
                $scope = CouponPricingScope::tryFrom((string) $coupon->getRawOriginal('pricing_scope')) ?? CouponPricingScope::All;
                $discountBase = match ($scope) {
                    CouponPricingScope::All => $newSubtotal,
                    CouponPricingScope::TicketsOnly => (int) $booking->items()->sum('price_minor_units'),
                    CouponPricingScope::ConcessionsOnly => (int) $booking->concessions()->sum('total_minor_units'),
                };
                $couponType = CouponType::from((string) $coupon->getRawOriginal('type'));
                $couponValue = (int) $coupon->getAttribute('value');
                $discount = $couponType === CouponType::Percentage
                    ? intdiv($discountBase * min(100, $couponValue), 100)
                    : $couponValue;

                if ($coupon->maximum_discount_minor_units !== null) {
                    $discount = min($discount, $coupon->maximum_discount_minor_units);
                }

                $discount = min($discount, $discountBase);
            }
        }
        $booking->forceFill([
            'subtotal_minor_units' => $newSubtotal,
            'discount_minor_units' => $discount,
            'total_minor_units' => $newSubtotal - $discount,
            'amount_minor_units' => $newSubtotal - $discount,
        ])->save();

        return $booking->refresh();
    }
}
