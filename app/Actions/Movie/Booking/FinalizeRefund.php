<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Inventory\InventoryStockMode;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\ScreeningSeat;
use App\Models\Payments\Payment;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;

final class FinalizeRefund
{
    /** @param array<string, mixed>|null $metadata */
    public function execute(Payment $payment, ?array $metadata = null): Payment
    {
        return DB::transaction(function () use ($payment, $metadata): Payment {
            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $items = $booking->items()->lockForUpdate()->get();

            if ($items->contains(fn ($item): bool => $item->getAttribute('status') === TicketStatus::CheckedIn)) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }

            if ($payment->getRawOriginal('status') !== PaymentStatus::Refunded->value) {
                $payment->forceFill([
                    'status' => PaymentStatus::Refunded,
                    'refunded_at' => $payment->refunded_at ?? now(),
                    'metadata' => $metadata ?? $payment->metadata,
                ])->save();
            }

            foreach ($items as $item) {
                $seat = ScreeningSeat::query()
                    ->whereKey($item->getAttribute('screening_seat_id'))
                    ->lockForUpdate()
                    ->first();

                if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Sold) {
                    $seat->forceFill(['status' => ScreeningSeatStatus::Available, 'sold_at' => null])->save();
                }

                if ($item->getAttribute('status') !== TicketStatus::Refunded) {
                    $item->setAttribute('status', TicketStatus::Refunded);
                    $item->save();
                }
            }

            foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
                $concession = Concession::query()
                    ->whereKey($line->getAttribute('concession_id'))
                    ->lockForUpdate()
                    ->first();

                if ($concession === null || $concession->getAttribute('stock') === null) {
                    continue;
                }

                $idempotencyKey = 'payment-refund-'.$payment->getKey().'-'.$line->getAttribute('concession_id');
                if (InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
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
