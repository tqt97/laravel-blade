<?php

namespace App\Http\Controllers\User;

use App\Actions\Booking\Lifecycle\CancelBooking;
use App\Exceptions\Booking\InvalidBookingTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CancelBookingRequest;
use App\Models\Booking\Booking;
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
