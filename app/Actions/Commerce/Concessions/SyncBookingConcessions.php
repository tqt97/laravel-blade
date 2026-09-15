<?php

namespace App\Actions\Commerce\Concessions;

use App\Actions\Commerce\Coupons\CalculateBookingDiscount;
use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Models\Booking\Booking;
use App\Models\Booking\BookingConcession;
use App\Models\Commerce\Concession;
use App\Models\Commerce\Coupon;
use App\Models\Inventory\InventoryMovement;
use App\Support\Booking\BookingMutationGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncBookingConcessions
{
    public function __construct(
        private readonly BookingMutationGuard $mutationGuard,
        private readonly CalculateBookingDiscount $discountCalculator,
    ) {}

    /** @param array<int, int> $quantitiesByConcession */
    public function execute(Booking $booking, array $quantitiesByConcession): Booking
    {
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

        $existingLines = $this->loadExistingLines($booking);
        $concessionIds = $this->concessionIds($existingLines, $quantitiesByConcession);
        $concessions = $this->loadConcessions($concessionIds);
        $this->validateQuantities($concessionIds, $quantitiesByConcession, $booking);

        $totalDelta = $this->syncLinesAndInventory(
            $booking,
            $concessionIds,
            $quantitiesByConcession,
            $existingLines,
            $concessions,
        );

        $this->updateBookingTotals($booking, $totalDelta);

        return $booking->refresh();
    }

    /** @return EloquentCollection<int, BookingConcession> */
    private function loadExistingLines(Booking $booking): EloquentCollection
    {
        return $booking->concessions()
            ->lockForUpdate()
            ->get()
            ->keyBy('concession_id');
    }

    /**
     * @param  EloquentCollection<int, BookingConcession>  $existingLines
     * @param  array<int, int>  $quantitiesByConcession
     * @return Collection<int, int>
     */
    private function concessionIds(EloquentCollection $existingLines, array $quantitiesByConcession): Collection
    {
        return $existingLines->keys()
            ->merge(array_keys($quantitiesByConcession))
            ->map(static fn (int|string $concessionId): int => (int) $concessionId)
            ->unique()
            ->sort()
            ->values();
    }

    /** @param Collection<int, int> $concessionIds */
    /** @return EloquentCollection<int, Concession> */
    private function loadConcessions(Collection $concessionIds): EloquentCollection
    {
        return Concession::query()
            ->whereIn('id', $concessionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, int>  $concessionIds
     * @param  array<int, int>  $quantitiesByConcession
     */
    private function validateQuantities(Collection $concessionIds, array $quantitiesByConcession, Booking $booking): void
    {
        $maxComboQuantity = (int) config('booking.limits.max_combo_quantity');
        $requestedComboCount = 0;

        foreach ($concessionIds as $concessionId) {
            $quantity = (int) ($quantitiesByConcession[$concessionId] ?? 0);

            if ($quantity < 0 || $quantity > $maxComboQuantity) {
                throw new BookingOperationFailed(__('booking.messages.combo_limit_per_seat'));
            }

            $requestedComboCount += $quantity;
        }

        $ticketCount = $booking->items()->count();
        $maxComboCount = $ticketCount * (int) config('booking.limits.max_combos_per_ticket');
        if ($requestedComboCount > $maxComboCount) {
            throw new BookingOperationFailed(__('booking.messages.combo_limit_per_seat'));
        }
    }

    /**
     * @param  Collection<int, int>  $concessionIds
     * @param  array<int, int>  $quantitiesByConcession
     * @param  EloquentCollection<int, BookingConcession>  $existingLines
     * @param  EloquentCollection<int, Concession>  $concessions
     */
    private function syncLinesAndInventory(
        Booking $booking,
        Collection $concessionIds,
        array $quantitiesByConcession,
        EloquentCollection $existingLines,
        EloquentCollection $concessions,
    ): int {
        $totalDelta = 0;

        foreach ($concessionIds as $concessionId) {
            $totalDelta += $this->syncLine(
                $booking,
                (int) ($quantitiesByConcession[$concessionId] ?? 0),
                $existingLines->get($concessionId),
                $concessions->get($concessionId),
            );
        }

        return $totalDelta;
    }

    private function syncLine(
        Booking $booking,
        int $desiredQuantity,
        ?BookingConcession $line,
        ?Concession $concession,
    ): int {
        if ($desiredQuantity > 0 && ($concession === null || ! $concession->is_active)) {
            throw new BookingOperationFailed(__('booking.messages.combo_unavailable'));
        }

        if ($concession === null) {
            return 0;
        }

        $this->assertCurrencyMatches($booking, $concession, $line);

        $currentQuantity = (int) ($line?->getAttribute('quantity') ?? 0);
        $currentTotal = (int) ($line?->getAttribute('total_minor_units') ?? 0);
        $unitPrice = (int) ($line?->getAttribute('unit_price_minor_units') ?? $concession->getAttribute('price_minor_units'));
        $quantityDelta = $desiredQuantity - $currentQuantity;

        if ($quantityDelta === 0) {
            return 0;
        }

        /** @var int|null $stock */
        $stock = $concession->getAttribute('stock');
        if ($quantityDelta > 0 && $stock !== null && $stock < $quantityDelta) {
            throw new BookingOperationFailed(__('booking.messages.combo_stock_unavailable'));
        }

        $newTotal = $unitPrice * $desiredQuantity;
        $this->persistBookingLine($booking, $concession, $line, $desiredQuantity, $unitPrice, $newTotal);
        $stockAfter = $this->updateStock($concession, $quantityDelta, $stock);
        $this->recordInventoryMovement($booking, $concession, $quantityDelta, $stock, $stockAfter);

        return $newTotal - $currentTotal;
    }

    private function assertCurrencyMatches(Booking $booking, Concession $concession, ?BookingConcession $line): void
    {
        if (strtoupper((string) $booking->currency) !== strtoupper((string) $concession->currency)) {
            throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
        }

        if ($line !== null && strtoupper((string) $line->currency) !== strtoupper((string) $booking->currency)) {
            throw new BookingOperationFailed(__('booking.messages.currency_mismatch'));
        }
    }

    private function persistBookingLine(
        Booking $booking,
        Concession $concession,
        ?BookingConcession $line,
        int $desiredQuantity,
        int $unitPrice,
        int $newTotal,
    ): void {
        if ($desiredQuantity === 0) {
            $line?->delete();
        } elseif ($line === null) {
            $booking->concessions()->create([
                'concession_id' => $concession->getKey(),
                'quantity' => $desiredQuantity,
                'unit_price_minor_units' => $unitPrice,
                'total_minor_units' => $newTotal,
                'currency' => strtoupper((string) $concession->getAttribute('currency')),
            ]);
        } else {
            $line->forceFill([
                'quantity' => $desiredQuantity,
                'total_minor_units' => $newTotal,
            ])->save();
        }
    }

    private function updateStock(Concession $concession, int $quantityDelta, ?int $stock): ?int
    {
        if ($stock === null || $quantityDelta === 0) {
            return $stock;
        }

        if ($quantityDelta > 0) {
            $concession->decrement('stock', $quantityDelta);
        } else {
            $concession->increment('stock', -$quantityDelta);
        }

        return $stock - $quantityDelta;
    }

    private function recordInventoryMovement(
        Booking $booking,
        Concession $concession,
        int $quantityDelta,
        ?int $stockBefore,
        ?int $stockAfter,
    ): void {
        InventoryMovement::query()->create([
            'concession_id' => $concession->getKey(),
            'booking_id' => $booking->getKey(),
            'type' => $quantityDelta > 0 ? InventoryMovementType::Reserve : InventoryMovementType::Release,
            'stock_mode' => $stockBefore === null ? InventoryStockMode::Unlimited : InventoryStockMode::Finite,
            'quantity_delta' => -$quantityDelta,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference' => 'booking-'.$booking->getKey(),
            // Request idempotency belongs to the booking mutation. The
            // inventory ledger must allow a valid 0->1->0->1 history.
            'idempotency_key' => 'booking:'.$booking->getKey().':concession:'.$concession->getKey().':mutation:'.(string) Str::uuid(),
        ]);
    }

    private function updateBookingTotals(Booking $booking, int $totalDelta): void
    {
        $newSubtotal = (int) $booking->getAttribute('subtotal_minor_units') + $totalDelta;
        $discount = $this->calculateDiscount($booking, $newSubtotal);

        $booking->forceFill([
            'subtotal_minor_units' => $newSubtotal,
            'discount_minor_units' => $discount,
            'total_minor_units' => $newSubtotal - $discount,
            'amount_minor_units' => $newSubtotal - $discount,
        ])->save();
    }

    private function calculateDiscount(Booking $booking, int $newSubtotal): int
    {
        $discount = (int) $booking->getAttribute('discount_minor_units');
        if ($booking->coupon_id === null) {
            return $discount;
        }

        $coupon = Coupon::query()->find($booking->coupon_id);
        if ($coupon === null) {
            return $discount;
        }

        return $this->discountCalculator->execute($booking, $coupon, $newSubtotal);
    }
}
