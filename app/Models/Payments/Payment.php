<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['payable_type', 'payable_id', 'provider', 'provider_payment_id', 'status', 'attempts', 'processing_started_at', 'last_attempt_at', 'amount_minor_units', 'currency', 'metadata', 'paid_at', 'refunded_at', 'failure_message'])]
class Payment extends Model
{
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function refundAttempts(): HasMany
    {
        return $this->hasMany(RefundAttempt::class);
    }

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'attempts' => 'integer', 'processing_started_at' => 'immutable_datetime', 'last_attempt_at' => 'immutable_datetime', 'metadata' => 'array', 'paid_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime'];
    }
}
