<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Jobs\ProcessStripeWebhook;
use App\Jobs\ReconcilePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
use App\Models\Payments\PaymentWebhookEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:reconcile {--limit=100}')]
#[Description('Reconcile provider payment status for incomplete payments.')]
class ReconcilePayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $payments = Payment::query()
            ->whereIn('status', PaymentStatus::reconciliationCandidates())
            ->whereNotNull('provider_payment_id')
            ->where(fn ($query) => $query->whereNull('next_reconcile_at')->orWhere('next_reconcile_at', '<=', now()))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        $orphanedPaymentIds = PaymentAttempt::query()
            ->whereNotNull('provider_payment_id')
            ->whereHas('payment', fn ($query) => $query
                ->whereIn('status', PaymentStatus::reconciliationCandidates())
                ->whereNull('provider_payment_id'))
            ->pluck('payment_id');

        $payments = $payments->merge($orphanedPaymentIds)->unique()->values();

        foreach ($payments as $paymentId) {
            ReconcilePayment::dispatch((int) $paymentId);
        }

        $orphanEvents = PaymentWebhookEvent::query()
            ->forProvider(PaymentProvider::Stripe)
            ->whereNull('processed_at')
            ->whereNull('failed_at')
            ->whereNotNull('provider_payment_id')
            ->oldest('id')
            ->limit((int) $this->option('limit'))
            ->pluck('event_id');

        foreach ($orphanEvents as $eventId) {
            ProcessStripeWebhook::dispatch((string) $eventId);
        }

        $this->info("Dispatched {$payments->count()} payment reconciliation job(s) and {$orphanEvents->count()} webhook reconciliation job(s).");

        return self::SUCCESS;
    }
}
