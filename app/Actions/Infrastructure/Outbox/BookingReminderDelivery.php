<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Mail\BookingReminderMail;
use App\Models\Booking\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class BookingReminderDelivery implements OutboxDeliveryHandler
{
    public function __construct(private readonly NotifyBookingOnce $notifyBookingOnce) {}

    public function execute(User $user, Booking $booking): void
    {
        $this->notifyBookingOnce->execute($user, $booking, 'booking_reminder');
        Mail::to($user)->send(new BookingReminderMail($booking));
    }
}
