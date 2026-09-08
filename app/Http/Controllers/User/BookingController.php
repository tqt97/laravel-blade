<?php

namespace App\Http\Controllers\User;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\ExpireBooking;
use App\Actions\Booking\PayBooking;
use App\Actions\Cinema\AddConcessions;
use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\AddConcessionsRequest;
use App\Http\Requests\User\CancelBookingRequest;
use App\Http\Requests\User\PayBookingRequest;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Queries\Cinema\UserBookingsQuery;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Carbon\CarbonImmutable;
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

    public function checkout(Booking $booking, ExpireBooking $expireBooking): View|RedirectResponse
    {
        $this->authorize('view', $booking);
        abort_unless(in_array((string) $booking->getRawOriginal('status'), [BookingStatus::Held->value, BookingStatus::PendingPayment->value], true), 404);
        $expiresAt = $booking->getRawOriginal('expires_at');
        if ($expiresAt !== null && CarbonImmutable::parse((string) $expiresAt, 'UTC')->isPast()) {
            $expireBooking->execute($booking);

            return to_route('user.bookings.show', $booking)->withErrors(['booking' => __('booking.messages.expired')]);
        }
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat']);

        return view('user.bookings.checkout', compact('booking'));
    }

    public function success(Booking $booking): View
    {
        $this->authorize('view', $booking);
        abort_unless($booking->getRawOriginal('status') === BookingStatus::Confirmed->value, 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat']);

        return view('user.bookings.success', compact('booking'));
    }

    public function combos(Booking $booking): View
    {
        $this->authorize('view', $booking);
        abort_unless($booking->getRawOriginal('status') === BookingStatus::Held->value, 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions']);
        $concessions = Concession::query()->where('is_active', true)->orderBy('name')->get();

        return view('user.bookings.combos', compact('booking', 'concessions'));
    }

    public function addCombos(AddConcessionsRequest $request, Booking $booking, AddConcessions $addConcessions): RedirectResponse
    {
        $this->authorize('confirm', $booking);
        $addConcessions->execute($booking, $request->validated('quantities', []));

        return to_route('user.bookings.show', $booking)->with('status', 'booking.messages.updated');
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

    public function pay(PayBookingRequest $request, Booking $booking, PayBooking $payBooking, ExpireBooking $expireBooking): RedirectResponse
    {
        $this->authorize('confirm', $booking);

        try {
            $payment = $payBooking->execute($booking, $request->validated('payment_method_id'));
        } catch (BookingExpired $exception) {
            $expireBooking->execute($booking);

            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        }

        if ($payment->getRawOriginal('status') !== PaymentStatus::Succeeded->value) {
            $message = $payment->getRawOriginal('status') === PaymentStatus::RequiresRefund->value
                ? __('booking.messages.payment_requires_refund')
                : __('booking.messages.payment_failed');

            throw ValidationException::withMessages(['payment' => $message]);
        }

        return to_route('user.bookings.success', $booking);
    }
}
