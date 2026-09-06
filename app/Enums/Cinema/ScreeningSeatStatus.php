<?php

namespace App\Enums\Cinema;

enum ScreeningSeatStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Sold = 'sold';
}
