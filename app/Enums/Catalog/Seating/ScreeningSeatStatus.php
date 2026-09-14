<?php

namespace App\Enums\Catalog\Seating;

enum ScreeningSeatStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Sold = 'sold';
}
