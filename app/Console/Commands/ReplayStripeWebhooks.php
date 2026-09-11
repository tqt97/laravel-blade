<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentProvider;
use App\Jobs\ProcessStripeWebhook;
use App\Models\Payments\PaymentWebhookEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:replay-webhooks {--event= : Replay one Stripe event ID} {--limit=100}')]
#[Description('Replay failed or orphaned Stripe webhook events.')]
final class ReplayStripeWebhooks extends Command
{
    public function handle(): int
    {
        $events = PaymentWebhookEvent::query()
            ->forProvider(PaymentProvider::Stripe)
            ->whereNull('processed_at')
            ->when($this->option('event'), fn ($query, string $eventId) => $query->where('event_id', $eventId))
            ->oldest('id')
            ->limit((int) $this->option('limit'))
            ->get(['id', 'event_id']);

        foreach ($events as $event) {
            $event->forceFill([
                'failed_at' => null,
                'failure_message' => null,
                'orphaned_at' => now(),
            ])->save();
            ProcessStripeWebhook::dispatch((string) $event->event_id);
        }

        $this->info("Dispatched {$events->count()} Stripe webhook replay job(s).");

        return self::SUCCESS;
    }
}
