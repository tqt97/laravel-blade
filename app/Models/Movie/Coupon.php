<?php

namespace App\Models\Movie;

use App\Enums\Movie\Booking\CouponType;
use Database\Factories\Movie\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'type', 'value', 'maximum_discount_minor_units', 'currency', 'usage_limit', 'used_count', 'starts_at', 'ends_at', 'is_active'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public function reservations(): HasMany
    {
        return $this->hasMany(CouponReservation::class);
    }

    public function scopeActive(Builder $query): void
    {
        $now = now()->utc();
        $query->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'maximum_discount_minor_units' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}
