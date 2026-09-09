<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payments\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RecoverStuckPayment
{
    public function execute(Payment $payment, ?CarbonImmutable $now = null): bool
    {
        return DB::transaction(function () use ($payment, $now): bool {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $status = PaymentStatus::tryFrom((string) $locked->getRawOriginal('status'));
            $startedAt = $locked->getRawOriginal('processing_started_at');
            $cutoff = ($now ?? now()->utc())->subMinutes((int) config('booking.payment.processing_timeout_minutes', 15));

            if (
                $status !== PaymentStatus::Processing
                || filled($locked->getRawOriginal('provider_payment_id'))
                || $startedAt === null
                || CarbonImmutable::parse((string) $startedAt, 'UTC')->isAfter($cutoff)
            ) {
                return false;
            }

            $locked->setAttribute('status', PaymentStatus::Unknown);
            $locked->setAttribute('processing_started_at', null);
            $locked->setAttribute('failure_message', 'Payment claim expired before a provider payment ID was recorded. Manual provider lookup is required.');

            $locked->save();

            $locked->syncLatestAttempt(PaymentAttemptStatus::Unknown, failureMessage: $locked->failure_message);

            return true;
        }, 3);
    }
}
