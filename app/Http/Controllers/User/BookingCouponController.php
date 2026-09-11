<?php

namespace App\Http\Controllers\User;

use App\Actions\Movie\Booking\ApplyCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ApplyCouponRequest;
use App\Models\Movie\Booking;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class BookingCouponController extends Controller
{
    public function store(ApplyCouponRequest $request, Booking $booking, ApplyCoupon $applyCoupon): RedirectResponse
    {
        $this->authorize('pay', $booking);

        try {
            $applyCoupon->execute($booking, $request->validated('code'));
        } catch (BookingOperationFailed $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return to_route('user.bookings.checkout', $booking)->with('status', 'booking.messages.coupon_applied');
    }
}
