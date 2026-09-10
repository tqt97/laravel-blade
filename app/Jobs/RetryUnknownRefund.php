<?php

namespace App\Jobs;

use App\Actions\Movie\Booking\RefundBooking;
use App\Models\Movie\Booking;
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
            $refundBooking->execute($booking);
        }
    }
}
