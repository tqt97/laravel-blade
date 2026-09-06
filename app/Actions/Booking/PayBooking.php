<?php

namespace App\Actions\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\ScreeningSeat;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Payments\Payment;
use App\Support\Payment\PaymentResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PayBooking
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function execute(Booking $booking, ?string $paymentMethodId = null): Payment
    {
        $payment = DB::transaction(function () use ($booking): Payment {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($status === BookingStatus::Confirmed) {
                return Payment::query()->firstOrCreate(['payable_type' => Booking::class, 'payable_id' => $booking->id], [
                    'provider' => config('booking.payment.provider', 'fake'), 'status' => PaymentStatus::Succeeded,
                    'amount_minor_units' => $booking->amount_minor_units, 'currency' => $booking->currency, 'paid_at' => now()->utc(),
                ]);
            }
            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                throw new RuntimeException('This booking cannot be paid.');
            }
            if ($booking->expires_at !== null && CarbonImmutable::parse($booking->getRawOriginal('expires_at'), 'UTC')->lessThanOrEqualTo(now()->utc())) {
                throw new RuntimeException('The booking hold has expired.');
            }
            $payment = Payment::query()->firstOrCreate(['payable_type' => Booking::class, 'payable_id' => $booking->id], [
                'provider' => config('booking.payment.provider', 'fake'), 'status' => PaymentStatus::Pending,
                'amount_minor_units' => $booking->amount_minor_units, 'currency' => $booking->currency,
            ]);
            if ($status === BookingStatus::Held) {
                $booking->transitionTo(BookingStatus::PendingPayment);
                $booking->save();
            }

            return $payment;
        }, 3);
        if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Succeeded) {
            return $payment;
        }

        if ($paymentMethodId !== null) {
            $metadata = $payment->getAttribute('metadata');
            $payment->setAttribute('metadata', array_merge(is_array($metadata) ? $metadata : [], ['payment_method_id' => $paymentMethodId]));
            $payment->save();
        }

        return $this->applyResult($payment, $this->gateway->charge($payment));
    }

    private function applyResult(Payment $payment, PaymentResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Succeeded) {
                return $payment;
            }
            $status = $result->status === 'succeeded' ? PaymentStatus::Succeeded : ($result->status === 'pending' ? PaymentStatus::Pending : PaymentStatus::Failed);
            $payment->setAttribute('status', $status);
            $payment->setAttribute('provider_payment_id', $result->providerPaymentId);
            $payment->setAttribute('metadata', $result->metadata);
            $payment->setAttribute('failure_message', $result->failureMessage);
            if ($status === PaymentStatus::Succeeded) {
                $payment->setAttribute('paid_at', now()->utc());
                $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();
                if (in_array(BookingStatus::from((string) $booking->getRawOriginal('status')), [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                    $booking->transitionTo(BookingStatus::Confirmed);
                    $booking->expires_at = null;
                    $booking->save();
                }
                $items = $booking->items()->with('screeningSeat')->lockForUpdate()->get();
                foreach ($items as $item) {
                    $screeningSeat = ScreeningSeat::query()->find($item->getAttribute('screening_seat_id'));
                    if ($screeningSeat === null) {
                        continue;
                    }
                    $screeningSeat->forceFill(['status' => ScreeningSeatStatus::Sold, 'held_until' => null, 'hold_token' => null, 'sold_at' => now()->utc()])->save();
                    if (str_starts_with((string) $item->getAttribute('ticket_code'), 'HOLD-')) {
                        $item->setAttribute('ticket_code', strtoupper('TKT-'.Str::random(20)));
                    }
                    $item->setAttribute('qr_token_hash', hash('sha256', (string) $item->getAttribute('ticket_code')));
                    $item->save();
                }
                OutboxMessage::query()->create([
                    'aggregate_type' => Booking::class, 'aggregate_id' => $payment->getAttribute('payable_id'),
                    'event_type' => 'booking.payment_succeeded', 'payload' => ['booking_id' => $payment->getAttribute('payable_id'), 'payment_id' => $payment->id],
                ]);
            }
            $payment->save();

            return $payment->refresh();
        }, 3);
    }
}
