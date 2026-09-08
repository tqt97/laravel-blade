<?php

namespace App\Http\Controllers\User;

use App\Actions\Booking\HoldSeats;
use App\Enums\Cinema\ScreeningStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\HoldSeatsRequest;
use App\Models\Cinema\Screening;
use App\Queries\Cinema\ScreeningBookingContextQuery;
use App\Support\Booking\SeatHoldConflict;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ScreeningController extends Controller
{
    public function index(): View
    {
        $screenings = Screening::query()->where('status', 'scheduled')->where('starts_at', '>', now()->utc())
            ->with(['movie:id,title,slug,duration_minutes,poster_path', 'room:id,name,code'])->orderBy('starts_at')->paginate(18);

        return view('user.screenings.index', compact('screenings'));
    }

    public function show(Screening $screening, ScreeningBookingContextQuery $bookingContext): View
    {
        $startsAt = $screening->getRawOriginal('starts_at');
        abort_unless($screening->getAttribute('status') === ScreeningStatus::Scheduled && $startsAt !== null && CarbonImmutable::parse((string) $startsAt, 'UTC')->isFuture(), 404);
        $screening->load(['movie', 'room', 'screeningSeats.seat']);
        $activeHold = $bookingContext->activeHold(request()->user(), $screening);
        $activeHoldSeatIds = $bookingContext->seatIds($activeHold);

        return view('user.screenings.show', compact('screening', 'activeHold', 'activeHoldSeatIds'));
    }

    public function hold(HoldSeatsRequest $request, Screening $screening, HoldSeats $holdSeats): RedirectResponse
    {
        try {
            $booking = $holdSeats->execute($request->user(), $screening, $request->validated('seat_ids'), $request->validated('idempotency_key'));
        } catch (SeatHoldConflict $exception) {
            throw ValidationException::withMessages(['seat_ids' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking);
    }
}
