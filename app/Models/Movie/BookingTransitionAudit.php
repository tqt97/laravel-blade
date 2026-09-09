<?php

namespace App\Models\Movie;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['booking_id', 'actor_id', 'from_status', 'to_status', 'reason'])]
class BookingTransitionAudit extends Model
{
    public $timestamps = false;
}
