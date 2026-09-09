<?php

namespace App\Enums\Movie\Seating;

enum SeatType: string
{
    case Regular = 'regular';
    case Vip = 'vip';
    case Couple = 'couple';
}
