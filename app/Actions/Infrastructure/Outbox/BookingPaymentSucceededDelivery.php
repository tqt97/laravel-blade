<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Mail\BookingConfirmationMail;
use App\Models\Booking\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class BookingPaymentSucceededDelivery implements OutboxDeliveryHandler
{
    public function __construct(private readonly NotifyBookingOnce $notifyBookingOnce) {}

    public function execute(User $user, Booking $booking): void
    {
        $this->notifyBookingOnce->execute($user, $booking, 'booking_confirmed');
        Mail::to($user)->send(new BookingConfirmationMail($booking));
    }
}
