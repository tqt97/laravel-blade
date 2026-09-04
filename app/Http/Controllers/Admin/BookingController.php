<?php

namespace App\Http\Controllers\Admin;

use App\Booking\Actions\CancelBooking;
use App\Booking\Models\Booking;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CancelBookingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(): View
    {
        $bookings = Booking::query()
            ->select(['id', 'user_id', 'resource_id', 'status', 'start_at', 'end_at', 'created_at'])
            ->with(['user:id,name,email', 'resource:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $cancelBooking->execute($booking, $request->validated('reason'));

        return back()->with('status', 'booking.messages.cancelled');
    }
}
