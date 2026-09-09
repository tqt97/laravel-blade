<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payments\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('payments:alert-stuck')]
#[Description('Report payments that have been processing too long.')]
class AlertStuckPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subMinutes((int) config('booking.payment.processing_timeout_minutes', 15));

        $count = Payment::query()->where('status', PaymentStatus::Processing)->where('processing_started_at', '<=', $cutoff)->count();

        if ($count > 0) {
            Log::warning('payments.stuck', ['count' => $count, 'cutoff' => $cutoff->toIso8601String()]);
        }

        $this->info("Found {$count} stuck payment(s).");

        return self::SUCCESS;
    }
}
