<?php

namespace App\Models\Payments;

use App\Enums\Payment\RefundAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'attempt_key', 'status', 'provider_refund_id', 'metadata', 'failure_message', 'started_at', 'completed_at'])]
class RefundAttempt extends Model
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'status' => RefundAttemptStatus::class,
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
