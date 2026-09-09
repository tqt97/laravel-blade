<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentAttemptStatus;
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

    public function syncLatestAttempt(PaymentAttemptStatus $status, ?string $providerPaymentId = null, ?string $failureMessage = null): void
    {
        $attempt = $this->attempts()->latest('id')->first();
        if ($attempt === null) {
            $attemptNumber = ((int) $this->getAttribute('attempts')) + 1;
            $this->attempts()->create([
                'attempt_key' => 'booking-payment-'.$this->getKey().'-'.$attemptNumber,
                'status' => $status,
                'provider_payment_id' => $providerPaymentId,
                'amount_minor_units' => $this->getAttribute('amount_minor_units'),
                'currency' => $this->getAttribute('currency'),
                'failure_message' => $failureMessage,
                'started_at' => now()->utc(),
                'completed_at' => $status === PaymentAttemptStatus::Processing ? null : now()->utc(),
            ]);
            $this->setAttribute('attempts', $attemptNumber);
            $this->save();

            return;
        }
        if ($attempt->getRawOriginal('status') !== PaymentAttemptStatus::Processing->value) {
            return;
        }

        $attempt->forceFill([
            'status' => $status,
            'provider_payment_id' => $providerPaymentId ?? $attempt->getAttribute('provider_payment_id'),
            'failure_message' => $failureMessage,
            'completed_at' => $status === PaymentAttemptStatus::Processing ? null : now()->utc(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'attempts' => 'integer',
            'processing_started_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'metadata' => 'array',
            'paid_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
