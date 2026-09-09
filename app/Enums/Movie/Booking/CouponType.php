<?php

namespace App\Enums\Movie\Booking;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
