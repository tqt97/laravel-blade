<?php

namespace App\Models\Cinema;

use App\Enums\Cinema\ScreeningSeatStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['screening_id', 'seat_id', 'status', 'hold_token', 'held_until', 'price_minor_units', 'currency', 'sold_at'])]
class ScreeningSeat extends Model
{
    use HasFactory;

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function bookingItem(): HasOne
    {
        return $this->hasOne(BookingItem::class);
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

        return CarbonImmutable::parse((string) $this->getRawOriginal('held_until'), 'UTC')->lessThanOrEqualTo($at ?? now()->utc());
    }

    protected function casts(): array
    {
        return ['status' => ScreeningSeatStatus::class, 'held_until' => 'immutable_datetime', 'sold_at' => 'immutable_datetime', 'price_minor_units' => 'integer'];
    }
}
