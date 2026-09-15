<?php

namespace App\Actions\Booking\Payment;

use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment\Payment;
use App\Support\Booking\BookingClock;
use Illuminate\Support\Facades\DB;

final class MarkPaymentProviderUnknown
{
    public function execute(Payment $payment, ?int $attemptId = null): Payment
    {
        return DB::transaction(function () use ($payment, $attemptId): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $status = PaymentStatus::tryFrom((string) $lockedPayment->getRawOriginal('status'));

            if ($status !== PaymentStatus::Processing && $status !== PaymentStatus::Unknown) {
                return $lockedPayment->refresh();
            }

            $attempt = $lockedPayment->attempts()->latest('id')->lockForUpdate()->first();
            if ($attempt === null || ($attemptId !== null && $attempt->getKey() !== $attemptId)) {
                return $lockedPayment->refresh();
            }

            $attemptStatus = PaymentAttemptStatus::tryFrom((string) $attempt->getRawOriginal('status'));
            if ($attemptStatus !== null && $attemptStatus->isTerminal()) {
                return $lockedPayment->refresh();
            }

            $message = __('booking.messages.payment_provider_unavailable');
            $now = BookingClock::now();

            $attempt->forceFill([
                'status' => PaymentAttemptStatus::Unknown,
                'failure_message' => $message,
                'completed_at' => null,
            ])->save();

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Processing,
                'failure_message' => $message,
                'processing_started_at' => $lockedPayment->processing_started_at ?? $now,
            ])->save();

            return $lockedPayment->refresh();
        }, 3);
    }
}
