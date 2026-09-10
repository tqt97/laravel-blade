<?php

namespace App\Models\Movie;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\StockAdjustmentAudit;
use Database\Factories\Movie\ConcessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'image_url', 'sku', 'price_minor_units', 'currency', 'stock', 'is_active'])]
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

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function stockAdjustmentAudits(): HasMany
    {
        return $this->hasMany(StockAdjustmentAudit::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForCurrency(Builder $query, string $currency): void
    {
        $query->where('currency', strtoupper($currency));
    }

    /** @param Builder<Concession> $query */
    public function scopeAvailableForBooking(Builder $query, string $currency): void
    {
        $query->active()->forCurrency($currency);
    }

    protected function casts(): array
    {
        return [
            'price_minor_units' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
