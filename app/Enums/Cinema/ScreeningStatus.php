<?php

namespace App\Enums\Cinema;

enum ScreeningStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
