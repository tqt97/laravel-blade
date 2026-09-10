<?php

namespace App\Models\Movie;

use App\Enums\Movie\Catalog\ScreeningStatus;
use App\Support\Time\BookingClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['movie_id', 'screening_room_id', 'starts_at', 'ends_at', 'status', 'base_price_minor_units', 'currency'])]
/** @property int $available_screening_seats_count */
class Screening extends Model
{
    use HasFactory;

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /** @return BelongsTo<ScreeningRoom, $this> */
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

    public function scopeBookable(Builder $query, ?CarbonImmutable $now = null): void
    {
        $query
            ->where('status', ScreeningStatus::Scheduled)
            ->whereHas('movie', fn (Builder $movieQuery): Builder => $movieQuery->where('is_active', true))
            ->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->where('is_active', true))
            ->where('starts_at', '>', self::bookableStartsAfter($now))
            ->where('starts_at', '<=', self::bookableStartsUntil($now));
    }

    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', ScreeningStatus::Scheduled);
    }

    public function scopeOverlapping(Builder $query, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    /** @param Builder<Screening> $query */
    public function scopeStartsAfter(Builder $query, ?CarbonImmutable $now = null): void
    {
        $query->where('starts_at', '>', $now ?? BookingClock::now());
    }

    public function isBookable(?CarbonImmutable $now = null): bool
    {
        $this->loadMissing(['movie:id,is_active', 'room:id,is_active']);
        $startsAt = $this->getRawOriginal('starts_at');
        $parsedStartsAt = BookingClock::parseStored($startsAt !== null ? (string) $startsAt : null);

        return $this->getRawOriginal('status') === ScreeningStatus::Scheduled->value
            && $this->movie?->is_active === true
            && $this->room?->is_active === true
            && $parsedStartsAt !== null
            && $parsedStartsAt->greaterThan(self::bookableStartsAfter($now))
            && $parsedStartsAt->lessThanOrEqualTo(self::bookableStartsUntil($now));
    }

    public static function bookableStartsAfter(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? BookingClock::now())->addMinutes((int) config('booking.minimum_lead_minutes'));
    }

    public static function bookableStartsUntil(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? BookingClock::now())->addDays((int) config('booking.maximum_horizon_days'));
    }

    protected function casts(): array
    {
        return [
            'status' => ScreeningStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'base_price_minor_units' => 'integer',
        ];
    }
}
