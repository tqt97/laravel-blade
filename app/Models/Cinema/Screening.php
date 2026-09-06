<?php

namespace App\Models\Cinema;

use App\Enums\Cinema\ScreeningStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['movie_id', 'screening_room_id', 'starts_at', 'ends_at', 'status', 'base_price_minor_units', 'currency'])]
class Screening extends Model
{
    use HasFactory;

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ScreeningRoom::class, 'screening_room_id');
    }

    public function screeningSeats(): HasMany
    {
        return $this->hasMany(ScreeningSeat::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ScreeningPrice::class);
    }

    protected function casts(): array
    {
        return ['status' => ScreeningStatus::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'base_price_minor_units' => 'integer'];
    }
}
