<?php

namespace App\Enums\Commerce;

enum CouponPricingScope: string
{
    case All = 'all';
    case TicketsOnly = 'tickets_only';
    case ConcessionsOnly = 'concessions_only';
}
