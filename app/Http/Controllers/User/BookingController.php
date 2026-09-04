<?php

namespace App\Http\Controllers\User;

use App\Booking\Actions\CancelBooking;
use App\Booking\Actions\ConfirmBooking;
use App\Booking\Actions\CreateBooking;
use App\Booking\Data\CreateBookingData;
use App\Booking\Exceptions\BookingConflict;
use App\Booking\Exceptions\BookingExpired;
use App\Booking\Exceptions\IdempotencyConflict;
use App\Booking\Exceptions\InvalidBookingTransition;
use App\Booking\Models\Booking;
use App\Booking\Queries\ActiveResourcesQuery;
use App\Booking\Queries\UserBookingsQuery;
use App\Booking\ValueObjects\BookingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CancelBookingRequest;
use App\Http\Requests\User\StoreBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BookingController extends Controller
{
    public function index(UserBookingsQuery $query): View
    {
        return view('user.bookings.index', ['bookings' => $query->paginate(request()->user())]);
    }

    public function create(ActiveResourcesQuery $query): View
    {
        return view('user.bookings.create', ['resources' => $query->get()]);
    }

    public function store(StoreBookingRequest $request, CreateBooking $createBooking): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $timezone = config('app.timezone', 'UTC');
            $period = BookingPeriod::fromDateTimes(
                CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['start_at'], $timezone),
                CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $validated['end_at'], $timezone),
            );

            $booking = $createBooking->execute($request->user(), new CreateBookingData(
                resourceId: (int) $validated['resource_id'],
                period: $period,
                idempotencyKey: $validated['idempotency_key'] ?? null,
            ));
        } catch (BookingConflict|IdempotencyConflict|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start_at' => $exception->getMessage()]);
        }

        return to_route('user.bookings.show', $booking)->with('status', 'booking.messages.created');
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);
        $booking->load('resource:id,name,slug,timezone');

        return view('user.bookings.show', compact('booking'));
    }

    public function confirm(Booking $booking, ConfirmBooking $confirmBooking): RedirectResponse
    {
        $this->authorize('confirm', $booking);

        try {
            $confirmBooking->execute($booking);
        } catch (BookingExpired|InvalidBookingTransition $exception) {
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        return back()->with('status', 'booking.messages.confirmed');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $cancelBooking->execute($booking, $request->validated('reason'));

        return back()->with('status', 'booking.messages.cancelled');
    }
}
