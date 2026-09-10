<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Payment\PaymentStatus;
use App\Jobs\ReconcilePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Support\Time\BookingClock;
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
            $cutoff = ($now ?? BookingClock::now())->subMinutes((int) config('booking.payment.processing_timeout_minutes', 15));

            if (
                $status !== PaymentStatus::Processing
                || $startedAt === null
                || BookingClock::parseStored((string) $startedAt)?->isAfter($cutoff)
            ) {
                return false;
            }

            if ($locked->reconciliation_attempted_at?->isAfter($cutoff)) {
                return false;
            }

            if (blank($locked->getRawOriginal('provider_payment_id'))) {
                /** @var PaymentAttempt|null $orphanedAttempt */
                $orphanedAttempt = $locked->attempts()
                    ->whereNotNull('provider_payment_id')
                    ->latest('id')
                    ->first();

                if ($orphanedAttempt !== null) {
                    $locked->forceFill([
                        'provider_payment_id' => $orphanedAttempt->provider_payment_id,
                        'status' => PaymentStatus::Pending,
                        'processing_started_at' => null,
                        'metadata' => $orphanedAttempt->metadata,
                        'reconciliation_attempted_at' => BookingClock::now(),
                        'reconciliation_attempts' => ((int) $locked->reconciliation_attempts) + 1,
                    ])->save();
                    ReconcilePayment::dispatch($locked->getKey())->afterCommit();

                    return true;
                }
            }

            if (filled($locked->getRawOriginal('provider_payment_id'))) {
                return false;
            }

            $locked->forceFill([
                'reconciliation_attempted_at' => BookingClock::now(),
                'reconciliation_attempts' => ((int) $locked->reconciliation_attempts) + 1,
                'failure_message' => 'Payment provider response was unknown. Reconciliation is in progress.',
            ])->save();
            ReconcilePayment::dispatch($locked->getKey())->afterCommit();

            return true;
        }, 3);
    }
}
