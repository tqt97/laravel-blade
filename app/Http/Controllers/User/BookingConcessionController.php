<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Concessions\AddConcessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\AddConcessionsRequest;
use App\Models\Movie\Booking;
use App\Queries\Movie\AvailableConcessionsQuery;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BookingConcessionController extends Controller
{
    public function availability(Booking $booking, AvailableConcessionsQuery $concessionsQuery): JsonResponse
    {
        $this->authorize('changeCombos', $booking);
        abort_unless($booking->getRawOriginal('status') === 'held', 404);

        $concessions = $concessionsQuery->availability($booking);
        $serverNow = now();

        return response()->json([
            'concessions' => $concessions,
            'availability_version' => hash('sha256', json_encode($concessions, JSON_THROW_ON_ERROR)),
            'updated_at' => $serverNow->toIso8601String(),
            'server_now' => $serverNow->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function index(Booking $booking, AvailableConcessionsQuery $concessionsQuery): View
    {
        $this->authorize('view', $booking);
        abort_unless($booking->getRawOriginal('status') === 'held', 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);
        $concessions = $concessionsQuery->get((string) ($booking->pricing_currency ?? $booking->currency));

        return view('user.bookings.combos', compact('booking', 'concessions'));
    }

    public function store(AddConcessionsRequest $request, Booking $booking, AddConcessions $addConcessions): RedirectResponse
    {
        $this->authorize('changeCombos', $booking);

        try {
            $addConcessions->execute($booking, $request->validated('quantities', []));
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        $destination = $request->string('return_to')->toString() === 'checkout' ? 'user.bookings.checkout' : 'user.bookings.show';

        return to_route($destination, $booking)->with('status', 'booking.messages.updated');
    }
}
