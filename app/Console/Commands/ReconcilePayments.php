<?php

namespace App\Console\Commands;

use App\Enums\Payment\PaymentStatus;
use App\Jobs\ReconcilePayment;
use App\Models\Payments\Payment;
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
            ->whereIn('status', [PaymentStatus::Processing->value, PaymentStatus::Pending->value, PaymentStatus::RequiresAction->value])
            ->whereNotNull('provider_payment_id')
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->pluck('id');
        foreach ($payments as $paymentId) {
            ReconcilePayment::dispatch((int) $paymentId);
        }
        $this->info("Dispatched {$payments->count()} payment reconciliation job(s).");

        return self::SUCCESS;
    }
}
