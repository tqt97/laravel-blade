<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentAttemptStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable(['payable_type', 'payable_id', 'provider', 'provider_payment_id', 'status', 'attempts', 'processing_started_at', 'last_attempt_at', 'reconciliation_attempted_at', 'reconciliation_attempts', 'amount_minor_units', 'currency', 'metadata', 'paid_at', 'refunded_at', 'failure_message'])]
class Payment extends Model
{
    public function scopeForProvider(Builder $query, PaymentProvider|string $provider): void
    {
        $provider = $provider instanceof PaymentProvider ? $provider : PaymentProvider::from($provider);

        $query->where($query->qualifyColumn('provider'), $provider->value);
    }

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
                'attempt_key' => config('booking.payment.attempt_key_prefix', 'booking-payment-').Str::uuid(),
                'status' => $status,
                'provider_payment_id' => $providerPaymentId,
                'amount_minor_units' => $this->getAttribute('amount_minor_units'),
                'currency' => $this->getAttribute('currency'),
                'failure_message' => $failureMessage,
                'started_at' => now(),
                'completed_at' => $status === PaymentAttemptStatus::Processing ? null : now(),
            ]);
            $this->setAttribute('attempts', $attemptNumber);

            $this->save();

            return;
        }
        if (! PaymentAttemptStatus::tryFrom((string) $attempt->getRawOriginal('status'))?->isOpen() === true) {
            return;
        }

        $attempt->forceFill([
            'status' => $status,
            'provider_payment_id' => $providerPaymentId ?? $attempt->getAttribute('provider_payment_id'),
            'failure_message' => $failureMessage,
            'completed_at' => $status === PaymentAttemptStatus::Processing ? null : now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'attempts' => 'integer',
            'processing_started_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'reconciliation_attempted_at' => 'immutable_datetime',
            'reconciliation_attempts' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
