<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileRefund;
use App\Jobs\RetryUnknownRefund;
use App\Models\Booking\Booking;
use App\Models\Payment\RefundAttempt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:retry-refunds {--limit=100 : Maximum refund operations to dispatch}')]
#[Description('Retry refund attempts whose provider response was unknown.')]
class RetryUnknownRefunds extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $attempts = RefundAttempt::query()
            ->reconciliationDue()
            ->whereHas('payment', fn ($query) => $query->where('payable_type', Booking::class))
            ->oldest('id')
            ->limit($limit)
            ->with('payment:id,payable_id')
            ->get();

        foreach ($attempts as $attempt) {
            $bookingId = $attempt->payment?->getAttribute('payable_id');

            if ($bookingId !== null) {
                if (filled($attempt->provider_refund_id)) {
                    ReconcileRefund::dispatch($attempt->getKey());
                } else {
                    RetryUnknownRefund::dispatch((int) $bookingId);
                }
            }
        }

        $this->info("Dispatched {$attempts->count()} refund retry job(s).");

        return self::SUCCESS;
    }
}
