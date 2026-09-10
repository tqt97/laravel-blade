<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\EditBookingSelection;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\HoldSeatsRequest;
use App\Models\Movie\Screening;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\SeatHoldConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ScreeningController extends Controller
{
    public function index(): View
    {
        $screenings = Screening::query()->bookable()
            ->with(['movie:id,title,slug,duration_minutes,poster_path', 'room:id,name,code'])->orderBy('starts_at')
            ->paginate((int) config('booking.listing.screenings_per_page'));

        return view('user.screenings.index', compact('screenings'));
    }

    public function show(Screening $screening): RedirectResponse
    {
        $screening->load('movie');

        return to_route('cinema.screenings.show', [$screening->movie, $screening]);
    }

    public function hold(HoldSeatsRequest $request, Screening $screening, EditBookingSelection $editBookingSelection): RedirectResponse
    {
        try {
            $booking = $editBookingSelection->execute(
                $request->user(),
                $screening,
                $request->validated('seat_ids'),
                $request->validated('idempotency_key'),
                $request->validated('quantities', [])
            );
        } catch (SeatHoldConflict $exception) {
            throw ValidationException::withMessages(['seat_ids' => $exception->getMessage()]);
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking);
    }
}
