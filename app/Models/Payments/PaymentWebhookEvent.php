<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed> $payload */
#[Fillable(['provider', 'event_id', 'provider_payment_id', 'payload', 'processed_at', 'orphaned_at', 'processing_attempts', 'last_attempt_at', 'failed_at', 'failure_message'])]
class PaymentWebhookEvent extends Model
{
    public function scopeForProvider(Builder $query, PaymentProvider|string $provider): void
    {
        $provider = $provider instanceof PaymentProvider ? $provider : PaymentProvider::from($provider);

        $query->where($query->qualifyColumn('provider'), $provider->value);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'orphaned_at' => 'immutable_datetime',
            'processing_attempts' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
