<?php

namespace App\Actions\Movie\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Inventory\InventoryMovementType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Models\Movie\ScreeningSeat;
use App\Models\Payments\Payment;
use App\Models\Payments\RefundAttempt;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Payment\PaymentResult;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RefundBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    public function execute(Booking $booking): Payment
    {
        /** @var array{payment: Payment, attempt: ?RefundAttempt, provider_already_refunded: bool} $claim */
        $claim = DB::transaction(function () use ($booking): array {
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('payable_type', Booking::class)->where('payable_id', $booking->getKey())->lockForUpdate()->firstOrFail();
            $paymentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if ($paymentStatus === PaymentStatus::Refunded) {
                return ['payment' => $payment, 'attempt' => null, 'provider_already_refunded' => false];
            }
            if (! in_array($paymentStatus, [PaymentStatus::Succeeded, PaymentStatus::RequiresRefund, PaymentStatus::Refunding], true)) {
                throw new BookingOperationFailed(__('booking.messages.refund_successful_only'));
            }
            $items = $booking->items()->lockForUpdate()->get();
            if ($items->contains(fn ($item): bool => $item->getAttribute('status') === TicketStatus::CheckedIn)) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }
            $existing = $payment->refundAttempts()->whereIn('status', [RefundAttemptStatus::Processing, RefundAttemptStatus::Unknown])->latest('id')->first();
            if ($existing?->getRawOriginal('status') === RefundAttemptStatus::Processing->value) {
                return ['payment' => $payment, 'attempt' => null, 'provider_already_refunded' => false];
            }
            if ($existing?->getRawOriginal('status') === RefundAttemptStatus::Unknown->value) {
                $existing->forceFill(['status' => RefundAttemptStatus::Processing, 'failure_message' => null, 'started_at' => now()])->save();

                return ['payment' => $payment, 'attempt' => $existing, 'provider_already_refunded' => false];
            }
            $succeededAttempt = $payment->refundAttempts()->where('status', RefundAttemptStatus::Succeeded)->latest('id')->first();
            if ($succeededAttempt !== null) {
                return ['payment' => $payment, 'attempt' => $succeededAttempt, 'provider_already_refunded' => true];
            }
            $attemptNumber = $payment->refundAttempts()->count() + 1;
            $attempt = $payment->refundAttempts()->create([
                'attempt_key' => 'booking-refund-'.$payment->id.'-'.$attemptNumber,
                'status' => RefundAttemptStatus::Processing,
                'started_at' => now(),
            ]);
            if ($this->stateMachine->canTransition($paymentStatus, PaymentStatus::Refunding)) {
                $payment->setAttribute('status', PaymentStatus::Refunding);
                $payment->save();
            }

            return ['payment' => $payment, 'attempt' => $attempt, 'provider_already_refunded' => false];
        }, 3);
        $payment = $claim['payment'];
        if ($claim['attempt'] === null) {
            return $payment;
        }

        if ($claim['provider_already_refunded']) {
            $result = new PaymentResult('refunded', $claim['attempt']->provider_refund_id, (array) $claim['attempt']->metadata);
        } else {
            try {
                $result = $this->gateway->refund($payment);
            } catch (Throwable $exception) {
                $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Unknown, 'failure_message' => 'Refund provider response was unknown.'])->save();
                report($exception);

                return $payment->refresh();
            }
        }

        if ($result->status !== 'refunded') {
            $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Failed, 'failure_message' => $result->failureMessage, 'metadata' => $result->metadata, 'completed_at' => now()])->save();
            $payment->forceFill([
                'status' => PaymentStatus::RequiresRefund,
                'failure_message' => $result->failureMessage,
            ])->save();
            throw new BookingOperationFailed($result->failureMessage ?? __('booking.messages.refund_failed'));
        }

        if (blank($result->providerPaymentId)) {
            $claim['attempt']->forceFill([
                'status' => RefundAttemptStatus::Unknown,
                'failure_message' => 'Refund succeeded without a provider refund ID. Reconciliation is required.',
                'metadata' => $result->metadata,
            ])->save();

            return $payment->refresh();
        }

        $claim['attempt']->forceFill(['status' => RefundAttemptStatus::Succeeded, 'provider_refund_id' => $result->providerPaymentId, 'metadata' => $result->metadata, 'completed_at' => now()])->save();

        return DB::transaction(function () use ($payment, $result): Payment {
            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Refunded) {
                return $payment;
            }

            $currentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if (! $this->stateMachine->canTransition($currentStatus, PaymentStatus::Refunded)) {
                return $payment;
            }

            $payment->setAttribute('status', PaymentStatus::Refunded);
            $payment->setAttribute('refunded_at', now());
            $payment->setAttribute('metadata', $result->metadata);
            $payment->save();

            if ($booking->items()->where('status', TicketStatus::CheckedIn)->exists()) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }

            foreach ($booking->items()->lockForUpdate()->get() as $item) {
                $seat = ScreeningSeat::query()
                    ->whereKey($item->getAttribute('screening_seat_id'))
                    ->lockForUpdate()
                    ->first();

                if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Sold) {
                    $seat->forceFill([
                        'status' => ScreeningSeatStatus::Available,
                        'sold_at' => null,
                    ])->save();
                }
                $item->setAttribute('status', TicketStatus::Refunded);
                $item->save();
            }
            foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
                $concession = Concession::query()->whereKey($line->getAttribute('concession_id'))->lockForUpdate()->first();

                $idempotencyKey = 'payment-refund-'.$payment->getKey().'-'.$line->getAttribute('concession_id');
                if ($concession !== null && $concession->getAttribute('stock') !== null && ! InventoryMovement::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                    $stockBefore = (int) $concession->stock;
                    $concession->increment('stock', (int) $line->getAttribute('quantity'));
                    InventoryMovement::query()->create([
                        'concession_id' => $concession->getKey(),
                        'booking_id' => $booking->getKey(),
                        'type' => InventoryMovementType::Refund,
                        'quantity_delta' => (int) $line->getAttribute('quantity'),
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore + (int) $line->getAttribute('quantity'),
                        'reference' => 'payment-'.$payment->getKey(),
                        'idempotency_key' => $idempotencyKey,
                    ]);
                }
            }
            $bookingStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if ($bookingStatus->canTransitionTo(BookingStatus::Cancelled)) {
                $booking->transitionTo(BookingStatus::Cancelled);
                $booking->setAttribute('cancellation_reason', __('booking.messages.payment_refunded_reason'));
                $booking->save();
            }

            return $payment->refresh();
        }, 3);
    }
}
