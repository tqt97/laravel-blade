<?php

namespace App\Models\Infrastructure;

use App\Enums\Infrastructure\OutboxEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['aggregate_type', 'aggregate_id', 'event_type', 'payload', 'available_at', 'claimed_at', 'published_at', 'attempts', 'failed_at', 'last_error'])]
class OutboxMessage extends Model
{
    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboxDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => OutboxEventType::class,
            'payload' => 'array',
            'available_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
