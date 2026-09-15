<?php

namespace App\Support\Payment;

use App\Enums\Payment\PaymentStatus;

final class PaymentStateMachine
{
    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        return $from->canTransitionTo($to);
    }
}
