<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentWebhookEvent;
use App\Support\Booking\BookingClock;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('payments:alert-stuck')]
#[Description('Report stuck payments, uncertain payment states and orphan Stripe webhooks.')]
class AlertStuckPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = BookingClock::now()->subMinutes((int) config('booking.observability.payment_anomaly_age_minutes', 15));
        $webhookCutoff = BookingClock::now()->subMinutes((int) config('booking.observability.orphan_webhook_age_minutes', 15));

        $stuckCount = Payment::query()
            ->where('status', PaymentStatus::Processing)
            ->where('processing_started_at', '<=', $cutoff)
            ->count();
        $unknownCount = Payment::query()
            ->where('status', PaymentStatus::Unknown)
            ->where('updated_at', '<=', $cutoff)
            ->count();
        $requiresRefundCount = Payment::query()
            ->where('status', PaymentStatus::RequiresRefund)
            ->count();
        $orphanWebhookCount = PaymentWebhookEvent::query()
            ->forProvider(PaymentProvider::Stripe)
            ->where(function ($query) use ($webhookCutoff): void {
                $query->whereNotNull('orphaned_at')
                    ->orWhere(function ($pending) use ($webhookCutoff): void {
                        $pending->whereNull('processed_at')
                            ->whereNull('failed_at')
                            ->where('created_at', '<=', $webhookCutoff);
                    });
            })
            ->count();

        $anomalies = [
            'stuck_processing' => $stuckCount,
            'unknown' => $unknownCount,
            'requires_refund' => $requiresRefundCount,
            'orphan_webhooks' => $orphanWebhookCount,
        ];

        if (array_sum($anomalies) > 0) {
            Log::warning('payments.anomalies', [
                ...$anomalies,
                'payment_cutoff' => $cutoff->toIso8601String(),
                'webhook_cutoff' => $webhookCutoff->toIso8601String(),
            ]);
        }

        $this->info(sprintf(
            'Found %d stuck, %d unknown, %d requiring refund and %d orphan webhook(s).',
            $stuckCount,
            $unknownCount,
            $requiresRefundCount,
            $orphanWebhookCount,
        ));

        return self::SUCCESS;
    }
}
