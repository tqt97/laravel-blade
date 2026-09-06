<?php

namespace App\Models\Infrastructure;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['aggregate_type', 'aggregate_id', 'event_type', 'payload', 'available_at', 'published_at', 'attempts', 'failed_at', 'last_error'])]
class OutboxMessage extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime'];
    }
}
