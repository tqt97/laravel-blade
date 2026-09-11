<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Movie\Booking\CancelBooking;
use App\Actions\Movie\Booking\RefundBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(): View
    {
        $bookings = Booking::query()
            ->select([
                'id',
                'user_id',
                'screening_id',
                'status',
                'expires_at',
                'total_minor_units',
                'pricing_currency',
                'created_at',
            ])
            ->with(['user:id,name,email', 'payment:id,payable_type,payable_id,status', 'screening.movie:id,title', 'screening.room:id,name,timezone'])
            ->latest()
            ->paginate((int) config('booking.listing.admin_page_size'))
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $cancelBooking->execute($booking, $request->validated('reason'));
        } catch (InvalidBookingTransition $exception) {
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.cancelled');
    }

    public function refund(Booking $booking, RefundBooking $refundBooking): RedirectResponse
    {
        $this->authorize('refund', $booking);

        try {
            $refundBooking->execute($booking);
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.refunded');
    }
}
