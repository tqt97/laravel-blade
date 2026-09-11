<?php

namespace App\Enums\Infrastructure;

enum OutboxEventType: string
{
    case BookingCreated = 'booking.created';
    case BookingStatusChanged = 'booking.status_changed';
    case BookingPaymentSucceeded = 'booking.payment_succeeded';
    case BookingReminderDue = 'booking.reminder_due';
    case BookingExpired = 'booking.expired';

    public function channel(): ?string
    {
        return match ($this) {
            self::BookingCreated => 'booking-created',
            self::BookingPaymentSucceeded => 'payment-succeeded',
            self::BookingReminderDue => 'booking-reminder',
            self::BookingExpired => 'booking-expired',
            self::BookingStatusChanged => null,
        };
    }

    public function shouldDispatch(): bool
    {
        return $this->channel() !== null;
    }
}
