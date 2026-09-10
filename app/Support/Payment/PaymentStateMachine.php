<?php

namespace App\Support\Payment;

use App\Enums\Payment\PaymentStatus;

final class PaymentStateMachine
{
    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return match ($from) {
            PaymentStatus::Pending => in_array($to, [PaymentStatus::Processing, PaymentStatus::RequiresAction, PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::Unknown], true),
            PaymentStatus::Processing => in_array($to, [PaymentStatus::Pending, PaymentStatus::RequiresAction, PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::Unknown], true),
            PaymentStatus::RequiresAction => in_array($to, [PaymentStatus::Processing, PaymentStatus::Pending, PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::Unknown], true),
            PaymentStatus::Failed => in_array($to, [PaymentStatus::Processing, PaymentStatus::Pending, PaymentStatus::RequiresAction, PaymentStatus::Succeeded, PaymentStatus::Unknown], true),
            PaymentStatus::Unknown => in_array($to, [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::RequiresAction, PaymentStatus::Succeeded, PaymentStatus::Failed, PaymentStatus::RequiresRefund], true),
            PaymentStatus::Succeeded => in_array($to, [PaymentStatus::RequiresRefund, PaymentStatus::Refunding, PaymentStatus::Refunded], true),
            PaymentStatus::RequiresRefund => in_array($to, [PaymentStatus::Refunding, PaymentStatus::Refunded], true),
            PaymentStatus::Refunding => in_array($to, [PaymentStatus::RequiresRefund, PaymentStatus::Refunded], true),
            PaymentStatus::Refunded => false,
        };
    }
}
