<?php

namespace App\Models\Movie;

use App\Enums\Movie\Concessions\InventoryMovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['concession_id', 'booking_id', 'actor_id', 'type', 'quantity_delta', 'stock_before', 'stock_after', 'reference', 'idempotency_key', 'metadata'])]
class ConcessionInventoryMovement extends Model
{
    public function concession(): BelongsTo
    {
        return $this->belongsTo(Concession::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'metadata' => 'array',
        ];
    }
}
