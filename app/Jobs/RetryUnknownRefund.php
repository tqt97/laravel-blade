<?php

namespace App\Jobs;

use App\Actions\Booking\Payment\RefundBooking;
use App\Models\Booking\Booking;
use App\Models\Payment\RefundAttempt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryUnknownRefund implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $bookingId) {}

    /**
     * Execute the job.
     */
    public function handle(RefundBooking $refundBooking): void
    {
        $booking = Booking::query()->find($this->bookingId);
        if ($booking !== null) {
            $attempt = $booking->payment === null ? null : RefundAttempt::query()
                ->where('payment_id', $booking->payment->getKey())
                ->reconciliationDue()
                ->latest('id')->first();
            if ($attempt !== null && filled($attempt->provider_refund_id)) {
                ReconcileRefund::dispatch($attempt->getKey());
            } else {
                $refundBooking->execute($booking);
            }
        }
    }
}
