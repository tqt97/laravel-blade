<?php

namespace App\Enums\Commerce;

enum CouponReservationStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Released = 'released';
}
