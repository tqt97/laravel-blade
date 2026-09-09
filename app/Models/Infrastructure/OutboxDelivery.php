<?php

namespace App\Models\Infrastructure;

use App\Enums\Infrastructure\OutboxDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outbox_message_id', 'channel', 'status', 'claimed_at', 'sent_at', 'last_error'])]
class OutboxDelivery extends Model
{
    public function outboxMessage(): BelongsTo
    {
        return $this->belongsTo(OutboxMessage::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OutboxDeliveryStatus::class,
            'claimed_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
