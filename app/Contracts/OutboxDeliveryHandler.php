<?php

namespace App\Contracts;

use App\Models\Booking\Booking;
use App\Models\User;

interface OutboxDeliveryHandler
{
    public function execute(User $user, Booking $booking): void;
}
