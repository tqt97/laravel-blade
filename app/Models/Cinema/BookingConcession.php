<?php

namespace App\Models\Cinema;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'concession_id', 'quantity', 'unit_price_minor_units', 'total_minor_units', 'currency'])]
class BookingConcession extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function concession(): BelongsTo
    {
        return $this->belongsTo(Concession::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_minor_units' => 'integer', 'total_minor_units' => 'integer'];
    }
}
