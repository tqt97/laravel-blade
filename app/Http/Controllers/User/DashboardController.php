<?php

namespace App\Http\Controllers\User;

use App\Enums\Booking\BookingStatus;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $upcomingBooking = request()->user()->bookings()
            ->select(['bookings.id', 'bookings.user_id', 'bookings.screening_id', 'bookings.status', 'bookings.total_minor_units', 'bookings.pricing_currency'])
            ->with(['screening:id,movie_id,screening_room_id,starts_at,ends_at', 'screening.movie:id,title', 'screening.room:id,name,timezone'])
            ->withCount(['items', 'concessions'])
            ->whereIn('bookings.status', [BookingStatus::Confirmed->value, BookingStatus::PendingPayment->value])
            ->whereHas('screening', fn ($query) => $query->where('starts_at', '>', now()->utc()))
            ->join('screenings', 'bookings.screening_id', '=', 'screenings.id')
            ->orderBy('screenings.starts_at')
            ->select('bookings.*')
            ->first();
        $recentBookings = request()->user()->bookings()
            ->select(['bookings.id', 'bookings.user_id', 'bookings.screening_id', 'bookings.status', 'bookings.total_minor_units', 'bookings.pricing_currency', 'bookings.created_at'])
            ->with(['screening:id,movie_id,screening_room_id,starts_at,ends_at', 'screening.movie:id,title', 'screening.room:id,name,timezone'])
            ->withCount(['items', 'concessions'])
            ->latest()
            ->limit(5)
            ->get();

        return view('user.dashboard', compact('upcomingBooking', 'recentBookings'));
    }
}
