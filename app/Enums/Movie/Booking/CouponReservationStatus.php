<?php

namespace App\Enums\Movie\Booking;

enum CouponReservationStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Released = 'released';
}
