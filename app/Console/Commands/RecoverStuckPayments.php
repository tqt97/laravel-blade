<?php

namespace App\Console\Commands;

use App\Actions\Movie\Booking\RecoverStuckPayment;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payments\Payment;
use App\Support\Time\BookingClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:recover-stuck {--limit=100}')]
#[Description('Dispatch reconciliation for payment claims without a provider ID after the timeout.')]
class RecoverStuckPayments extends Command
{
    public function handle(RecoverStuckPayment $recoverStuckPayment): int
    {
        $cutoff = BookingClock::now()->subMinutes((int) config('booking.payment.processing_timeout_minutes', 15));

        $payments = Payment::query()
            ->where('status', PaymentStatus::Processing)
            ->whereNull('provider_payment_id')
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '<=', $cutoff)
            ->oldest('processing_started_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $recovered = 0;
        foreach ($payments as $payment) {
            $recovered += (int) $recoverStuckPayment->execute($payment);
        }

        $this->info("Dispatched reconciliation for {$recovered} stuck payment claim(s).");

        return self::SUCCESS;
    }
}
