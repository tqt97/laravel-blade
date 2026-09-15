<?php

namespace App\Enums\Commerce;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
