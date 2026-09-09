<?php

namespace App\Models\Movie;

use App\Enums\Movie\Booking\CouponReservationStatus;
use Database\Factories\Movie\CouponReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['coupon_id', 'booking_id', 'status'])]
class CouponReservation extends Model
{
    /** @use HasFactory<CouponReservationFactory> */
    use HasFactory;

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['status' => CouponReservationStatus::class];
    }
}
