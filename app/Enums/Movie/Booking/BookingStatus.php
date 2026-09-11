<?php

namespace App\Enums\Movie\Booking;

enum BookingStatus: string
{
    case Held = 'held';
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function occupiesResource(): bool
    {
        return in_array($this, [self::Held, self::PendingPayment, self::Confirmed], true);
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Held, self::PendingPayment], true);
    }

    public function isTicketAccessible(): bool
    {
        return in_array($this, [self::Confirmed, self::Completed], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::NoShow], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Held => in_array($target, [self::PendingPayment, self::Confirmed, self::Cancelled, self::Expired], true),
            self::PendingPayment => in_array($target, [self::Confirmed, self::Cancelled, self::Expired], true),
            self::Confirmed => in_array($target, [self::Cancelled, self::Completed, self::NoShow], true),
            default => false,
        };
    }
}
