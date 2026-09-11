<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\ExpireBooking;
use App\Enums\Movie\Booking\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Movie\Booking;
use App\Queries\Movie\AvailableConcessionsQuery;
use App\Queries\Movie\UserBookingsQuery;
use App\Support\Time\BookingClock;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function index(UserBookingsQuery $query): View
    {
        return view('user.bookings.index', [
            'bookings' => $query->paginate(request()->user(), request()->string('status')->toString() ?: null),
        ]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);

        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);

        return view('user.bookings.show', compact('booking'));
    }

    public function checkout(Booking $booking, ExpireBooking $expireBooking, AvailableConcessionsQuery $concessionsQuery): View
    {
        $this->authorize('view', $booking);

        abort_unless(BookingStatus::tryFrom((string) $booking->getRawOriginal('status'))?->isPayable() === true, 404);

        $expiresAt = $booking->getRawOriginal('expires_at');
        $expiresAtInstant = BookingClock::parseStored($expiresAt !== null ? (string) $expiresAt : null);

        if ($expiresAtInstant !== null && $expiresAtInstant->lessThanOrEqualTo(BookingClock::now())) {
            $booking->load(['screening.movie', 'screening.room']);
            $expireBooking->execute($booking);
            $canRebook = $booking->screening?->isBookable() === true;

            return view('user.bookings.expired', compact('booking', 'canRebook'));
        }

        $booking->loadMissing('screening');

        if (! $booking->screening?->isBookable()) {
            $booking->loadMissing(['screening.movie', 'screening.room']);
            $canRebook = false;

            return view('user.bookings.expired', compact('booking', 'canRebook'));
        }

        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);

        $concessions = $concessionsQuery->get((string) ($booking->pricing_currency ?? $booking->currency));

        return view('user.bookings.checkout', compact('booking', 'concessions'));
    }

    public function success(Booking $booking): View
    {
        $this->authorize('view', $booking);

        abort_unless($booking->getRawOriginal('status') === BookingStatus::Confirmed->value, 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);

        return view('user.bookings.success', compact('booking'));
    }
}
