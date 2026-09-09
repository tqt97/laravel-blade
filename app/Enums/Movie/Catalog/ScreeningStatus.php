<?php

namespace App\Enums\Movie\Catalog;

enum ScreeningStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
