<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Cinema\CheckInTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckInTicketRequest;
use Illuminate\Http\RedirectResponse;

final class TicketController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CheckInTicketRequest $request, CheckInTicket $checkInTicket): RedirectResponse
    {
        $checkInTicket->execute($request->validated('ticket_code'), $request->user()->id);

        return back()->with('status', 'booking.messages.checked_in');
    }
}
