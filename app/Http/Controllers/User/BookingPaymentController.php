<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\ExpireBooking;
use App\Actions\Movie\Booking\PayBooking;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\PayBookingRequest;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\BookingExpired;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BookingPaymentController extends Controller
{
    public function action(Booking $booking): View
    {
        $this->authorize('confirm', $booking);
        $booking->loadMissing(['screening.movie', 'screening.room', 'items.screeningSeat.seat']);

        $payment = $booking->payment;
        abort_unless($payment !== null, 404);
        abort_unless(PaymentStatus::tryFrom((string) $payment->getRawOriginal('status'))?->isAwaitingProviderResolution() === true, 404);

        $paymentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));

        return view('user.bookings.payment-action', [
            'booking' => $booking,
            'payment' => $payment,
            'clientSecret' => $paymentStatus === PaymentStatus::Unknown ? null : $payment->getAttribute('client_secret'),
        ]);
    }

    public function status(Booking $booking): JsonResponse
    {
        $this->authorize('confirm', $booking);
        $payment = $booking->payment;
        abort_unless($payment !== null, 404);

        $status = (string) $payment->getRawOriginal('status');
        $bookingStatus = BookingStatus::tryFrom((string) $booking->getRawOriginal('status'));
        $redirect = $bookingStatus === BookingStatus::Expired || $bookingStatus === BookingStatus::Cancelled
            ? route('user.bookings.show', $booking)
            : ($bookingStatus?->isPayable() !== true
                ? route('user.bookings.checkout', $booking)
            : match ($status) {
                PaymentStatus::Succeeded->value => route('user.bookings.success', $booking),
                PaymentStatus::Failed->value, PaymentStatus::RequiresRefund->value => route('user.bookings.checkout', $booking),
                default => null,
            });

        return response()->json(['status' => $status, 'redirect' => $redirect, 'booking_status' => $bookingStatus?->value]);
    }

    public function pay(PayBookingRequest $request, Booking $booking, PayBooking $payBooking, ExpireBooking $expireBooking): RedirectResponse
    {
        $this->authorize('pay', $booking);

        try {
            $payment = $payBooking->execute($booking, $request->validated('payment_method_id'), $request->validated('quantities', []));
        } catch (BookingExpired $exception) {
            $expireBooking->execute($booking);
            throw ValidationException::withMessages(['booking' => $exception->getMessage()]);
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['quantities' => $exception->getMessage()]);
        }

        $paymentStatus = (string) $payment->getRawOriginal('status');
        if (PaymentStatus::tryFrom($paymentStatus)?->isAwaitingProviderResolution() === true) {
            return to_route('user.bookings.payment-action', $booking);
        }

        if ($paymentStatus !== PaymentStatus::Succeeded->value) {
            $message = $paymentStatus === PaymentStatus::RequiresRefund->value
                ? __('booking.messages.payment_requires_refund')
                : __('booking.messages.payment_failed');
            throw ValidationException::withMessages(['payment' => $message]);
        }

        return to_route('user.bookings.success', $booking);
    }
}
