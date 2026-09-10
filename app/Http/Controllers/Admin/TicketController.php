<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Movie\Ticketing\CheckInTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckInTicketRequest;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class TicketController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CheckInTicketRequest $request, CheckInTicket $checkInTicket): RedirectResponse
    {
        try {
            $checkInTicket->execute(
                $request->validated('ticket_code'),
                $request->user()->id
            );
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['ticket_code' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.checked_in');
    }
}
