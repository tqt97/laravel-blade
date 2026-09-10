<?php

namespace App\Models\Movie;

use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Support\Time\BookingClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['screening_id', 'seat_id', 'status', 'hold_token', 'held_by_booking_id', 'held_until', 'price_minor_units', 'currency', 'sold_at'])]
class ScreeningSeat extends Model
{
    use HasFactory;

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function bookingItem(): HasOne
    {
        return $this->hasOne(BookingItem::class);
    }

    public function scopeAvailableForSelection(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= BookingClock::now();
        $query->where(function (Builder $query) use ($at): void {
            $query->where('status', ScreeningSeatStatus::Available)
                ->orWhere(fn (Builder $heldQuery) => $heldQuery
                    ->where('status', ScreeningSeatStatus::Held)
                    ->where('held_until', '<=', $at));
        });
    }

    public function isAvailableForSelection(?CarbonImmutable $at = null): bool
    {
        $status = ScreeningSeatStatus::tryFrom((string) $this->getRawOriginal('status'));
        if ($status === ScreeningSeatStatus::Available) {
            return true;
        }

        if ($status !== ScreeningSeatStatus::Held || $this->getRawOriginal('held_until') === null) {
            return false;
        }

        return BookingClock::parseStored((string) $this->getRawOriginal('held_until'))?->lessThanOrEqualTo($at ?? BookingClock::now()) ?? false;
    }

    protected function casts(): array
    {
        return [
            'status' => ScreeningSeatStatus::class,
            'held_by_booking_id' => 'integer',
            'held_until' => 'immutable_datetime',
            'sold_at' => 'immutable_datetime',
            'price_minor_units' => 'integer',
        ];
    }
}
