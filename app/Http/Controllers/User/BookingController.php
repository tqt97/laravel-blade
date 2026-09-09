<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\ApplyCoupon;
use App\Actions\Movie\Booking\CancelBooking;
use App\Actions\Movie\Booking\ExpireBooking;
use App\Actions\Movie\Booking\PayBooking;
use App\Actions\Movie\Concessions\AddConcessions;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\AddConcessionsRequest;
use App\Http\Requests\User\ApplyCouponRequest;
use App\Http\Requests\User\CancelBookingRequest;
use App\Http\Requests\User\PayBookingRequest;
use App\Models\Movie\Booking;
use App\Models\Movie\Concession;
use App\Queries\Movie\UserBookingsQuery;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Booking\Exceptions\InvalidBookingTransition;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BookingController extends Controller
{
    public function index(UserBookingsQuery $query): View
    {
        return view('user.bookings.index', ['bookings' => $query->paginate(request()->user(), request()->string('status')->toString() ?: null)]);
    }

    public function show(Booking $booking): View
    {
        $this->authorize('view', $booking);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);

        return view('user.bookings.show', compact('booking'));
    }

    public function checkout(Booking $booking, ExpireBooking $expireBooking): View
    {
        $this->authorize('view', $booking);
        abort_unless(in_array((string) $booking->getRawOriginal('status'), [BookingStatus::Held->value, BookingStatus::PendingPayment->value], true), 404);
        $expiresAt = $booking->getRawOriginal('expires_at');
        if ($expiresAt !== null && CarbonImmutable::parse((string) $expiresAt, 'UTC')->isPast()) {
            $booking->load(['screening.movie', 'screening.room']);
            $expireBooking->execute($booking);
            $canRebook = $booking->screening?->isBookable() === true;

            return view('user.bookings.expired', compact('booking', 'canRebook'));
        }
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);
        $concessions = Concession::query()
            ->active()
            ->forCurrency((string) ($booking->pricing_currency ?? $booking->currency))
            ->select(['id', 'name', 'image_url', 'price_minor_units', 'currency', 'stock'])
            ->orderBy('name')
            ->limit((int) config('booking.listing.concessions_per_page'))
            ->get();

        return view('user.bookings.checkout', compact('booking', 'concessions'));
    }

    public function success(Booking $booking): View
    {
        $this->authorize('view', $booking);
        abort_unless($booking->getRawOriginal('status') === BookingStatus::Confirmed->value, 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);

        return view('user.bookings.success', compact('booking'));
    }

    public function paymentAction(Booking $booking): View
    {
        $this->authorize('confirm', $booking);
        $payment = $booking->payment;
        abort_unless($payment !== null, 404);
        abort_unless(in_array((string) $payment->getRawOriginal('status'), [PaymentStatus::RequiresAction->value, PaymentStatus::Pending->value, PaymentStatus::Processing->value, PaymentStatus::Unknown->value], true), 404);
        $metadata = $payment->getAttribute('metadata');

        return view('user.bookings.payment-action', [
            'booking' => $booking,
            'payment' => $payment,
            'clientSecret' => is_array($metadata) ? ($metadata['client_secret'] ?? null) : null,
        ]);
    }

    public function paymentStatus(Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);
        $payment = $booking->payment;
        abort_unless($payment !== null, 404);

        return response()->json(['status' => $payment->getRawOriginal('status'), 'redirect' => $payment->getRawOriginal('status') === PaymentStatus::Succeeded->value ? route('user.bookings.success', $booking) : null]);
    }

    public function comboAvailability(Booking $booking): JsonResponse
    {
        $this->authorize('changeCombos', $booking);
        abort_unless($booking->getRawOriginal('status') === BookingStatus::Held->value, 404);

        $selectedQuantities = $booking->concessions()->pluck('quantity', 'concession_id');
        $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
        $availability = Concession::query()
            ->active()
            ->forCurrency($currency)
            ->select(['id', 'stock'])
            ->get(['id', 'stock'])
            ->mapWithKeys(function (Concession $concession) use ($selectedQuantities): array {
                $selected = (int) ($selectedQuantities[$concession->getKey()] ?? 0);
                $stock = $concession->stock;

                return [(string) $concession->getKey() => [
                    'stock' => $stock,
                    'selected' => $selected,
                    'max' => $stock === null ? (int) config('booking.limits.max_combo_quantity') : min((int) config('booking.limits.max_combo_quantity'), $selected + (int) $stock),
                ]];
            });

        return response()->json([
            'concessions' => $availability,
            'updated_at' => now()->utc()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function combos(Booking $booking): View
    {
        $this->authorize('view', $booking);
        abort_unless($booking->getRawOriginal('status') === BookingStatus::Held->value, 404);
        $booking->load(['screening.movie', 'screening.room', 'items.screeningSeat.seat', 'concessions.concession']);
        $concessions = Concession::query()
            ->active()
            ->forCurrency((string) ($booking->pricing_currency ?? $booking->currency))
            ->select(['id', 'name', 'image_url', 'price_minor_units', 'currency', 'stock'])
            ->orderBy('name')
            ->limit((int) config('booking.listing.concessions_per_page'))
            ->get();

        return view('user.bookings.combos', compact('booking', 'concessions'));
    }

    public function applyCoupon(ApplyCouponRequest $request, Booking $booking, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $this->authorize('pay', $booking);
        try {
            $applyCoupon->execute($booking, $request->validated('code'));
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking)->with('status', 'booking.messages.coupon_applied');
    }

    public function addCombos(AddConcessionsRequest $request, Booking $booking, AddConcessions $addConcessions): RedirectResponse
    {
        $this->authorize('changeCombos', $booking);
        try {
            $addConcessions->execute($booking, $request->validated('quantities', []));
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        $destination = $request->string('return_to')->toString() === 'checkout'
            ? 'user.bookings.checkout'
            : 'user.bookings.show';

        return to_route($destination, $booking)->with('status', 'booking.messages.updated');
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
        $this->authorize('pay', $booking);

        try {
            $payment = $payBooking->execute(
                $booking,
                $request->validated('payment_method_id'),
                $request->validated('quantities', []),
            );
        } catch (BookingExpired $exception) {
            $expireBooking->execute($booking);

            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        $paymentStatus = (string) $payment->getRawOriginal('status');
        if ($paymentStatus === PaymentStatus::RequiresAction->value || in_array($paymentStatus, [PaymentStatus::Pending->value, PaymentStatus::Processing->value, PaymentStatus::Unknown->value], true)) {
            return to_route('user.bookings.payment-action', $booking);
        }
        if ($paymentStatus !== PaymentStatus::Succeeded->value) {
            $message = $payment->getRawOriginal('status') === PaymentStatus::RequiresRefund->value
                ? __('booking.messages.payment_requires_refund')
                : __('booking.messages.payment_failed');

            throw ValidationException::withMessages(['payment' => $message]);
        }

        return to_route('user.bookings.success', $booking);
    }
}
