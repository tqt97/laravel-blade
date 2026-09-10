<?php

namespace App\Enums\Infrastructure;

enum OutboxEventType: string
{
    case BookingCreated = 'booking.created';
    case BookingStatusChanged = 'booking.status_changed';
    case BookingPaymentSucceeded = 'booking.payment_succeeded';
    case BookingReminderDue = 'booking.reminder_due';
    case BookingExpired = 'booking.expired';
}
