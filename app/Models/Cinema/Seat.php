<?php

namespace App\Models\Cinema;

use App\Enums\Cinema\SeatType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['screening_room_id', 'row_label', 'seat_number', 'seat_type', 'price_minor_units', 'is_active'])]
class Seat extends Model
{
    use HasFactory;

    public function room(): BelongsTo
    {
        return $this->belongsTo(ScreeningRoom::class, 'screening_room_id');
    }

    public function screeningSeats(): HasMany
    {
        return $this->hasMany(ScreeningSeat::class);
    }

    protected function casts(): array
    {
        return ['seat_type' => SeatType::class, 'seat_number' => 'integer', 'price_minor_units' => 'integer', 'is_active' => 'boolean'];
    }
}
