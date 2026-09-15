<?php

namespace App\Actions\Infrastructure\Outbox;

use App\Contracts\OutboxDeliveryHandler;
use App\Models\Booking\Booking;
use App\Models\User;

final class BookingExpiredDelivery implements OutboxDeliveryHandler
{
    public function __construct(private readonly NotifyBookingOnce $notifyBookingOnce) {}

    public function execute(User $user, Booking $booking, string $idempotencyKey): void
    {
        $this->notifyBookingOnce->execute($user, $booking, 'booking_expired');
    }
}
