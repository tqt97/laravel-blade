<?php

namespace App\Models\Payments;

use App\Enums\Payment\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'attempt_key', 'status', 'provider_payment_id', 'payment_method_reference', 'amount_minor_units', 'currency', 'metadata', 'request_metadata', 'response_metadata', 'failure_message', 'started_at', 'completed_at'])]
class PaymentAttempt extends Model
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function attemptKey(): string
    {
        return (string) $this->getAttribute('attempt_key');
    }

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount_minor_units' => 'integer',
            'metadata' => 'array',
            'request_metadata' => 'array',
            'response_metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
