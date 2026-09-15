<?php

namespace App\Console\Commands;

use App\Models\Payment\PaymentWebhookEvent;
use App\Support\Booking\BookingClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:prune-webhooks')]
#[Description('Prune processed payment webhook payloads past the retention period')]
class PrunePaymentWebhookEvents extends Command
{
    public function handle(): int
    {
        $retentionDays = max(1, (int) config('booking.observability.webhook_retention_days', 90));
        $cutoff = BookingClock::now()->subDays($retentionDays);
        $deleted = 0;

        PaymentWebhookEvent::query()
            ->where(function ($query): void {
                $query->whereNotNull('processed_at')->orWhereNotNull('failed_at');
            })
            ->where(function ($query) use ($cutoff): void {
                $query->where('processed_at', '<', $cutoff)->orWhere('failed_at', '<', $cutoff);
            })
            ->chunkById(500, function ($events) use (&$deleted): void {
                $deleted += PaymentWebhookEvent::query()->whereKey($events->modelKeys())->delete();
            });

        $this->info("Pruned {$deleted} payment webhook events.");

        return self::SUCCESS;
    }
}
