<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\CancelBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CancelBookingRequest;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class BookingCancellationController extends Controller
{
    public function __invoke(CancelBookingRequest $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $this->authorize('cancel', $booking);

        try {
            $cancelBooking->execute($booking, $request->validated('reason'));
        } catch (InvalidBookingTransition $exception) {
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.cancelled');
    }
}
