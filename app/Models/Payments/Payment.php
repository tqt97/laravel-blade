<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['payable_type', 'payable_id', 'provider', 'provider_payment_id', 'provider_status', 'status', 'attempts', 'processing_started_at', 'last_attempt_at', 'reconciliation_attempted_at', 'reconciliation_attempts', 'next_reconcile_at', 'reconciliation_deadline', 'last_reconciliation_error', 'amount_minor_units', 'currency', 'metadata', 'provider_metadata', 'client_secret', 'paid_at', 'refunded_at', 'failure_message'])]
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

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'attempts' => 'integer',
            'processing_started_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'reconciliation_attempted_at' => 'immutable_datetime',
            'reconciliation_attempts' => 'integer',
            'next_reconcile_at' => 'immutable_datetime',
            'reconciliation_deadline' => 'immutable_datetime',
            'metadata' => 'array',
            'provider_metadata' => 'array',
            'client_secret' => 'encrypted',
            'paid_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
