<?php

namespace App\Models\Movie;

use App\Enums\Movie\Ticketing\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'screening_seat_id', 'ticket_code', 'qr_token_hash', 'qr_payload_version', 'price_minor_units', 'currency', 'status', 'checked_in_at', 'checked_in_by'])]
class BookingItem extends Model
{
    use HasFactory;

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function screeningSeat(): BelongsTo
    {
        return $this->belongsTo(ScreeningSeat::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'checked_in_at' => 'immutable_datetime',
            'price_minor_units' => 'integer',
        ];
    }
}
