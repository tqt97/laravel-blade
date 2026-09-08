<?php

namespace App\Actions\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Cinema\Booking;
use App\Models\Payments\Payment;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Payment\PaymentResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PayBooking
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly FinalizeSuccessfulPayment $finalizeSuccessfulPayment,
    ) {}

    public function execute(Booking $booking, ?string $paymentMethodId = null): Payment
    {
        $payment = DB::transaction(function () use ($booking): Payment {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $status = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($status === BookingStatus::Confirmed) {
                return Payment::query()->firstOrCreate(['payable_type' => Booking::class, 'payable_id' => $booking->id], [
                    'provider' => config('booking.payment.provider', 'fake'),
                    'status' => PaymentStatus::Succeeded,
                    'amount_minor_units' => $booking->amount_minor_units,
                    'currency' => $booking->currency,
                    'paid_at' => now()->utc(),
                ]);
            }
            if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                throw new RuntimeException('This booking cannot be paid.');
            }
            if ($booking->expires_at !== null && CarbonImmutable::parse($booking->getRawOriginal('expires_at'), 'UTC')->lessThanOrEqualTo(now()->utc())) {
                throw new BookingExpired('The booking hold has expired.');
            }
            $payment = Payment::query()->firstOrCreate([
                'payable_type' => Booking::class,
                'payable_id' => $booking->id,
            ], [
                'provider' => config('booking.payment.provider', 'fake'),
                'status' => PaymentStatus::Pending,
                'amount_minor_units' => $booking->amount_minor_units,
                'currency' => $booking->currency,
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

        $payment = $this->applyResult($payment, $this->gateway->charge($payment));

        return $payment->getRawOriginal('status') === PaymentStatus::Succeeded->value
            ? $this->finalizeSuccessfulPayment->execute($payment)
            : $payment;
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
            }
            $payment->save();

            return $payment->refresh();
        }, 3);
    }
}
