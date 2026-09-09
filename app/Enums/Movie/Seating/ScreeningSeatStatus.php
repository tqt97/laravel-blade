<?php

namespace App\Enums\Movie\Seating;

enum ScreeningSeatStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Sold = 'sold';
}
