<?php

namespace App\Enums\Cinema;

enum SeatType: string
{
    case Regular = 'regular';
    case Vip = 'vip';
    case Couple = 'couple';
}
