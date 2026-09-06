<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['payable_type', 'payable_id', 'provider', 'provider_payment_id', 'status', 'amount_minor_units', 'currency', 'metadata', 'paid_at', 'refunded_at', 'failure_message'])]
class Payment extends Model
{
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'metadata' => 'array', 'paid_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime'];
    }
}
