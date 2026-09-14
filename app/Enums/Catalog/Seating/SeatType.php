<?php

namespace App\Enums\Catalog\Seating;

enum SeatType: string
{
    case Regular = 'regular';
    case Vip = 'vip';
    case Couple = 'couple';
}
