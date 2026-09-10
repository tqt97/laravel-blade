<?php

namespace App\Models\Movie;

use App\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'synopsis', 'duration_minutes', 'rating', 'genre', 'director', 'cast', 'language', 'format', 'poster_path', 'backdrop_path', 'trailer_url', 'release_date', 'is_active'])]
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

    public function getPosterUrlAttribute(): ?string
    {
        if (blank($this->poster_path)) {
            return null;
        }

        return Str::startsWith($this->poster_path, ['http://', 'https://'])
            ? $this->poster_path
            : asset('storage/'.$this->poster_path);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Movie> $query */
    public function scopeHasBookableScreenings(Builder $query): void
    {
        $query->whereIn('id', Screening::query()->bookable()->select('movie_id'));
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'release_date' => 'date',
            'is_active' => 'boolean',
            'cast' => 'array',
        ];
    }

    public function getBackdropUrlAttribute(): ?string
    {
        if (blank($this->backdrop_path)) {
            return null;
        }

        return Str::startsWith($this->backdrop_path, ['http://', 'https://'])
            ? $this->backdrop_path
            : asset('storage/'.$this->backdrop_path);
    }
}
