<?php

namespace App\Booking\Models;

use App\Booking\Enums\BookingStatus;
use App\Models\BookableResource;
use App\Models\User;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'resource_id', 'status', 'start_at', 'end_at', 'expires_at', 'idempotency_key', 'idempotency_hash', 'cancellation_reason'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return BookingFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
