<?php

namespace App\Console\Commands;

use App\Enums\Payment\RefundAttemptStatus;
use App\Jobs\RetryUnknownRefund;
use App\Models\Movie\Booking;
use App\Models\Payments\RefundAttempt;
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
            ->where('status', RefundAttemptStatus::Unknown)
            ->whereHas('payment', fn ($query) => $query->where('payable_type', Booking::class))
            ->oldest('id')
            ->limit($limit)
            ->with('payment:id,payable_id')
            ->get();

        foreach ($attempts as $attempt) {
            $bookingId = $attempt->payment?->getAttribute('payable_id');
            if ($bookingId !== null) {
                RetryUnknownRefund::dispatch((int) $bookingId);
            }
        }

        $this->info("Dispatched {$attempts->count()} refund retry job(s).");

        return self::SUCCESS;
    }
}
