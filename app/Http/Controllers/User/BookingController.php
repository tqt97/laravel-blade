<?php

namespace App\Http\Controllers\User;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\ConfirmBooking;
use App\Actions\Booking\PayBooking;
use App\Enums\Booking\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CancelBookingRequest;
use App\Http\Requests\User\PayBookingRequest;
use App\Models\Cinema\Booking;
use App\Queries\Cinema\UserBookingsQuery;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function index(UserBookingsQuery $query): View
    {
        return view('user.bookings.index', ['bookings' => $query->paginate(request()->user())]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat']);

        return view('user.bookings.show', compact('booking'));
    }

    public function confirm(Booking $booking, ConfirmBooking $confirmBooking, PayBooking $payBooking): RedirectResponse
    {
        $this->authorize('confirm', $booking);

        try {
            if ((int) $booking->getAttribute('amount_minor_units') > 0) {
                $payBooking->execute($booking);

                return back()->with('status', 'booking.messages.paid');
            }
            $confirmedBooking = $confirmBooking->execute($booking);
            if ($confirmedBooking->getRawOriginal('status') === BookingStatus::Expired->value) {
                throw new BookingExpired('The booking hold has expired.');
            }
        } catch (BookingExpired|InvalidBookingTransition $exception) {
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.confirmed');
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

    public function pay(PayBookingRequest $request, Booking $booking, PayBooking $payBooking): RedirectResponse
    {
        $this->authorize('confirm', $booking);
        $payBooking->execute($booking, $request->validated('payment_method_id'));

        return back()->with('status', 'booking.messages.paid');
    }
}
