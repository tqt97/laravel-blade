<?php

namespace App\Models\Movie;

use App\Concerns\HasSlug;
use App\Enums\Movie\Catalog\ScreeningStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'slug', 'synopsis', 'duration_minutes', 'rating', 'poster_path', 'release_date', 'is_active'])]
#[Hidden(['deleted_at'])]
class Movie extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected function slugSourceColumn(): string
    {
        return 'title';
    }

    /** @return HasMany<Screening, $this> */
    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeHasBookableScreenings(Builder $query): void
    {
        $query->whereHas('screenings', fn (Builder $screeningQuery): Builder => $screeningQuery
            ->where('status', ScreeningStatus::Scheduled)
            ->where('starts_at', '>', now()->utc()->addMinutes((int) config('booking.minimum_lead_minutes')))
            ->where('starts_at', '<=', now()->utc()->addDays((int) config('booking.maximum_horizon_days'))));
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'release_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
