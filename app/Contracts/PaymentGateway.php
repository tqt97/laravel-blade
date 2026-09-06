<?php

namespace App\Contracts;

use App\Models\Payments\Payment;
use App\Support\Payment\PaymentResult;

interface PaymentGateway
{
    public function charge(Payment $payment): PaymentResult;

    public function refund(Payment $payment): PaymentResult;
}
