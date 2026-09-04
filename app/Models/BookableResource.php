<?php

namespace App\Models;

use App\Booking\Models\Booking;
use Database\Factories\BookableResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'timezone', 'is_active'])]
#[Hidden(['deleted_at'])]
class BookableResource extends Model
{
    /** @use HasFactory<BookableResourceFactory> */
    use HasFactory, SoftDeletes;

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
