<?php

namespace App\Models\Cinema;

use Database\Factories\Cinema\ConcessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'sku', 'price_minor_units', 'currency', 'stock', 'is_active'])]
class Concession extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ConcessionFactory::new();
    }

    public function bookingConcessions(): HasMany
    {
        return $this->hasMany(BookingConcession::class);
    }

    protected function casts(): array
    {
        return ['price_minor_units' => 'integer', 'stock' => 'integer', 'is_active' => 'boolean'];
    }
}
