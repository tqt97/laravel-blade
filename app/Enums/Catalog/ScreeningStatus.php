<?php

namespace App\Enums\Catalog;

enum ScreeningStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
