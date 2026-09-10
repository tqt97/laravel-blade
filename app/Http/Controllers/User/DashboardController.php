<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Queries\Movie\UserBookingsQuery;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(UserBookingsQuery $bookings): View
    {
        $user = request()->user();
        $upcomingBooking = $bookings->upcoming($user);
        $recentBookings = $bookings->recent($user);

        return view('user.dashboard', compact('upcomingBooking', 'recentBookings'));
    }
}
