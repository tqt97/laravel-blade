<?php

namespace App\Models\Cinema;

use App\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class);
    }

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'release_date' => 'date', 'is_active' => 'boolean'];
    }
}
