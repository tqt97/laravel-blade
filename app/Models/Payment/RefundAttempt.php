<?php

namespace App\Models\Payment;

use App\Enums\Payment\RefundAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'attempt_key', 'status', 'provider_refund_id', 'metadata', 'failure_message', 'started_at', 'completed_at', 'next_reconcile_at', 'reconciliation_attempts'])]
class RefundAttempt extends Model
{
    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', RefundAttemptStatus::openStatuses());
    }

    public function scopeReconciliationDue(Builder $query): void
    {
        $query->whereIn('status', RefundAttemptStatus::openStatuses())->where(function (Builder $query): void {
            $query->whereNull('next_reconcile_at')->orWhere('next_reconcile_at', '<=', now());
        });
    }

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
            'next_reconcile_at' => 'immutable_datetime',
            'reconciliation_attempts' => 'integer',
        ];
    }
}
