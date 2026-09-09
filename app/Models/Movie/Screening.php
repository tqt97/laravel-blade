<?php

namespace App\Models\Movie;

use App\Enums\Movie\Catalog\ScreeningStatus;
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

    public function scopeBookable(Builder $query, ?CarbonImmutable $now = null): void
    {
        $query
            ->where('status', ScreeningStatus::Scheduled)
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

    public function isBookable(?CarbonImmutable $now = null): bool
    {
        $startsAt = $this->getRawOriginal('starts_at');
        $parsedStartsAt = $startsAt !== null ? CarbonImmutable::parse((string) $startsAt, 'UTC') : null;

        return $this->getRawOriginal('status') === ScreeningStatus::Scheduled->value
            && $parsedStartsAt !== null
            && $parsedStartsAt->greaterThan(self::bookableStartsAfter($now))
            && $parsedStartsAt->lessThanOrEqualTo(self::bookableStartsUntil($now));
    }

    public static function bookableStartsAfter(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now('UTC'))->addMinutes((int) config('booking.minimum_lead_minutes'));
    }

    public static function bookableStartsUntil(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now('UTC'))->addDays((int) config('booking.maximum_horizon_days'));
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
