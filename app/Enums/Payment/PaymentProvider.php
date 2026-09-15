<?php

namespace App\Enums\Payment;

enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case Fake = 'fake';

    public static function configured(): self
    {
        return self::from((string) config('booking.payment.provider', self::Stripe->value));
    }
}
