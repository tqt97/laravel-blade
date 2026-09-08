<?php

namespace App\Actions\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\ConcessionInventoryMovement;
use App\Models\Cinema\ScreeningSeat;
use App\Models\Payments\Payment;
use App\Models\Payments\RefundAttempt;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RefundBooking
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function execute(Booking $booking): Payment
    {
        /** @var array{payment: Payment, attempt: ?RefundAttempt, resources_released: bool} $claim */
        $claim = DB::transaction(function () use ($booking): array {
            $payment = Payment::query()->where('payable_type', Booking::class)->where('payable_id', $booking->getKey())->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $paymentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
            if ($paymentStatus === PaymentStatus::Refunded) {
                return ['payment' => $payment, 'attempt' => null, 'resources_released' => false];
            }
            if (! in_array($paymentStatus, [PaymentStatus::Succeeded, PaymentStatus::RequiresRefund], true)) {
                throw new BookingOperationFailed(__('booking.messages.refund_successful_only'));
            }
            $items = $booking->items()->lockForUpdate()->get();
            if ($items->contains(fn ($item): bool => $item->getAttribute('status') === TicketStatus::CheckedIn)) {
                throw new BookingOperationFailed(__('booking.messages.checked_in_cannot_refund'));
            }
            $existing = $payment->refundAttempts()->whereIn('status', ['processing', 'unknown'])->latest('id')->first();
            if ($existing !== null) {
                return ['payment' => $payment, 'attempt' => null, 'resources_released' => false];
            }
            $attemptNumber = $payment->refundAttempts()->count() + 1;
            $attempt = $payment->refundAttempts()->create([
                'attempt_key' => 'booking-refund-'.$payment->id.'-'.$attemptNumber,
                'status' => 'processing',
                'started_at' => now()->utc(),
            ]);

            return ['payment' => $payment, 'attempt' => $attempt, 'resources_released' => $paymentStatus === PaymentStatus::RequiresRefund];
        }, 3);
        $payment = $claim['payment'];
        if ($claim['attempt'] === null) {
            return $payment;
        }

        try {
            $result = $this->gateway->refund($payment);
        } catch (Throwable $exception) {
            $claim['attempt']->forceFill(['status' => 'unknown', 'failure_message' => 'Refund provider response was unknown.'])->save();
            report($exception);

            return $payment->refresh();
        }

        if ($result->status !== 'refunded') {
            $claim['attempt']->forceFill(['status' => 'failed', 'failure_message' => $result->failureMessage, 'metadata' => $result->metadata, 'completed_at' => now()->utc()])->save();
            throw new BookingOperationFailed($result->failureMessage ?? __('booking.messages.refund_failed'));
        }

        $claim['attempt']->forceFill(['status' => 'succeeded', 'provider_refund_id' => $result->providerPaymentId, 'metadata' => $result->metadata, 'completed_at' => now()->utc()])->save();

        return DB::transaction(function () use ($payment, $result, $claim): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Refunded) {
                return $payment;
            }

            $payment->setAttribute('status', PaymentStatus::Refunded);
            $payment->setAttribute('refunded_at', now()->utc());
            $payment->setAttribute('metadata', $result->metadata);
            $payment->save();

            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();

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
            if (! $claim['resources_released']) {
                foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
                    $concession = Concession::query()->whereKey($line->getAttribute('concession_id'))->lockForUpdate()->first();

                    if ($concession !== null && $concession->getAttribute('stock') !== null) {
                        $stockBefore = (int) $concession->stock;
                        $concession->increment('stock', (int) $line->getAttribute('quantity'));
                        ConcessionInventoryMovement::query()->create([
                            'concession_id' => $concession->getKey(),
                            'booking_id' => $booking->getKey(),
                            'type' => 'refund',
                            'quantity_delta' => (int) $line->getAttribute('quantity'),
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockBefore + (int) $line->getAttribute('quantity'),
                            'reference' => 'payment-'.$payment->getKey(),
                        ]);
                    }
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
