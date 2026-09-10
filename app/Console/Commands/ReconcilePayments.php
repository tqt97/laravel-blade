<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentStatus;
use App\Jobs\ReconcilePayment;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentAttempt;
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
            ->whereIn('status', [PaymentStatus::Processing, PaymentStatus::Pending, PaymentStatus::RequiresAction, PaymentStatus::Unknown])
            ->whereNotNull('provider_payment_id')
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');

        $orphanedPaymentIds = PaymentAttempt::query()
            ->whereNotNull('provider_payment_id')
            ->whereHas('payment', fn ($query) => $query
                ->whereIn('status', [PaymentStatus::Processing, PaymentStatus::Pending, PaymentStatus::RequiresAction, PaymentStatus::Unknown])
                ->whereNull('provider_payment_id'))
            ->pluck('payment_id');

        $payments = $payments->merge($orphanedPaymentIds)->unique()->values();

        foreach ($payments as $paymentId) {
            ReconcilePayment::dispatch((int) $paymentId);
        }

        $this->info("Dispatched {$payments->count()} payment reconciliation job(s).");

        return self::SUCCESS;
    }
}
