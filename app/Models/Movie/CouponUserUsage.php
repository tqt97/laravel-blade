<?php

namespace App\Models\Movie;

use App\Enums\Movie\Booking\CouponReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['coupon_id', 'user_id', 'booking_id', 'status'])]
class CouponUserUsage extends Model
{
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    protected function casts(): array
    {
        return ['status' => CouponReservationStatus::class];
    }
}
