<?php

namespace App\Actions\Booking\Payment;

use App\Actions\Booking\Lifecycle\TransitionBooking;
use App\Enums\Booking\BookingStatus;
use App\Enums\Catalog\Seating\ScreeningSeatStatus;
use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Ticketing\TicketStatus;
use App\Exceptions\Booking\BookingOperationFailed;
use App\Models\Booking\Booking;
use App\Models\Booking\ScreeningSeat;
use App\Models\Commerce\Concession;
use App\Models\Inventory\InventoryMovement;
use App\Models\Payment\Payment;
use App\Support\Booking\BookingClock;
use Illuminate\Support\Facades\DB;

final class FinalizeRefund
{
    /** @param array<string, mixed>|null $metadata */
    public function execute(Payment $payment, ?array $metadata = null): Payment
    {
        return DB::transaction(function () use ($payment, $metadata): Payment {
            $booking = Booking::query()
                ->whereKey($payment->getAttribute('payable_id'))
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $items = $booking->items()->lockForUpdate()->get();
            $seats = ScreeningSeat::query()
                ->whereIn('id', $items->pluck('screening_seat_id')->unique()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->contains(fn ($item): bool => $item->getAttribute('status') === TicketStatus::CheckedIn)) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }

            if ($payment->getRawOriginal('status') !== PaymentStatus::Refunded->value) {
                $payment->forceFill([
                    'status' => PaymentStatus::Refunded,
                    'refunded_at' => $payment->refunded_at ?? BookingClock::now(),
                    'metadata' => $metadata ?? $payment->metadata,
                ])->save();
            }

            foreach ($items as $item) {
                $seat = $seats->get($item->getAttribute('screening_seat_id'));

                if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Sold) {
                    $seat->forceFill([
                        'status' => ScreeningSeatStatus::Available,
                        'sold_at' => null,
                    ])->save();
                }

                if ($item->getAttribute('status') !== TicketStatus::Refunded) {
                    $item->setAttribute('status', TicketStatus::Refunded);
                    $item->save();
                }
            }

            $lines = $booking->concessions()->lockForUpdate()->get();
            $concessions = Concession::query()
                ->whereIn('id', $lines->pluck('concession_id')->unique()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $movementKeys = $lines->mapWithKeys(fn ($line): array => [
                'payment-refund-'.$payment->getKey().'-'.$line->getAttribute('concession_id') => true,
            ]);
            $existingMovementKeys = InventoryMovement::query()
                ->whereIn('idempotency_key', $movementKeys->keys())
                ->pluck('idempotency_key')
                ->flip();

            foreach ($lines as $line) {
                $concession = $concessions->get($line->getAttribute('concession_id'));

                if ($concession === null || $concession->getAttribute('stock') === null) {
                    continue;
                }

                $idempotencyKey = 'payment-refund-'.$payment->getKey().'-'.$line->getAttribute('concession_id');
                if ($existingMovementKeys->has($idempotencyKey)) {
                    continue;
                }

                $stockBefore = (int) $concession->stock;
                $quantity = (int) $line->getAttribute('quantity');
                $concession->increment('stock', $quantity);

                InventoryMovement::query()->create([
                    'concession_id' => $concession->getKey(),
                    'booking_id' => $booking->getKey(),
                    'type' => InventoryMovementType::Refund,
                    'stock_mode' => InventoryStockMode::Finite,
                    'quantity_delta' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore + $quantity,
                    'reference' => 'payment-'.$payment->getKey(),
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            $bookingStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($bookingStatus->canTransitionTo(BookingStatus::Cancelled)) {
                $booking->setAttribute('cancellation_reason', __('booking.messages.payment_refunded_reason'));

                app(TransitionBooking::class)->execute($booking, BookingStatus::Cancelled, $booking->cancellation_reason);
            }

            return $payment->refresh();
        }, 3);
    }
}
