<?php

namespace App\Jobs;

use App\Actions\Booking\Payment\FinalizeRefund;
use App\Enums\Payment\RefundAttemptStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\RefundAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class FinalizeRefundAttempt implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $refundAttemptId) {}

    public function handle(FinalizeRefund $finalizeRefund): void
    {
        $attempt = RefundAttempt::query()->find($this->refundAttemptId);
        if ($attempt === null || RefundAttemptStatus::tryFrom((string) $attempt->getRawOriginal('status')) !== RefundAttemptStatus::Succeeded) {
            return;
        }

        $payment = Payment::query()->find($attempt->getAttribute('payment_id'));
        if ($payment !== null) {
            $finalizeRefund->execute($payment, (array) $attempt->getAttribute('metadata'));
        }
    }
}
