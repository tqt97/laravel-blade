<?php

namespace App\Actions\Booking\Payment;

use App\Actions\Booking\Lifecycle\ReleaseBookingResources;
use App\Actions\Booking\Lifecycle\TransitionBooking;
use App\Enums\Booking\BookingStatus;
use App\Enums\Catalog\Seating\ScreeningSeatStatus;
use App\Enums\Commerce\CouponReservationStatus;
use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Ticketing\TicketStatus;
use App\Models\Booking\Booking;
use App\Models\Booking\ScreeningSeat;
use App\Models\Commerce\Coupon;
use App\Models\Commerce\CouponReservation;
use App\Models\Commerce\CouponUserUsage;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Payment\Payment;
use App\Support\Booking\BookingClock;
use App\Support\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FinalizeSuccessfulPayment
{
    public function __construct(
        private readonly ReleaseBookingResources $resourceReleaser,
        private readonly PaymentStateMachine $stateMachine,
    ) {}

    public function execute(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $booking = Booking::query()
                ->whereKey($payment->getAttribute('payable_id'))
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->getRawOriginal('status') !== PaymentStatus::Succeeded->value) {
                return $payment;
            }

            if (blank($payment->getRawOriginal('provider_payment_id'))) {
                $payment->forceFill([
                    'status' => PaymentStatus::Unknown,
                    'failure_message' => __('booking.messages.payment_provider_missing_id'),
                ])->save();

                return $payment->refresh();
            }

            $bookingStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if (
                $bookingStatus->isTicketAccessible()
                && ! $booking->items()->where('ticket_code', 'like', 'HOLD-%')->exists()
            ) {
                return $payment;
            }

            if ($this->bookingCannotBeFinalized($booking, $bookingStatus)) {
                if ($bookingStatus->isPayable()) {
                    $this->expireAndReleaseBooking($booking);
                }

                $metadata = $payment->getAttribute('metadata');
                $payment->setAttribute('status', PaymentStatus::RequiresRefund);
                $payment->setAttribute('failure_message', __('booking.messages.payment_after_expiry'));
                $payment->setAttribute('metadata', array_merge(is_array($metadata) ? $metadata : [], [
                    'requires_refund' => true,
                    'requires_refund_reason' => 'booking_expired_before_finalization',
                ]));
                $payment->save();

                return $payment->refresh();
            }

            $items = $booking->items()->lockForUpdate()->get();
            // Lock all booking items first, then all seats in a stable order.
            // This avoids one query per ticket while preserving a consistent
            // lock order for hold/edit/refund flows.
            $seats = ScreeningSeat::query()
                ->whereIn('id', $items->pluck('screening_seat_id')->unique()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $seat = $seats->get($item->getAttribute('screening_seat_id'));

                if ($seat === null) {
                    $this->expireAndReleaseBooking($booking);

                    return $this->markRequiresRefund($payment, __('booking.messages.seat_released_before_payment_finalization'));
                }

                if (
                    $seat->getAttribute('status') !== ScreeningSeatStatus::Held ||
                    (int) $seat->getAttribute('held_by_booking_id') !== $booking->getKey()
                ) {
                    $this->expireAndReleaseBooking($booking);

                    return $this->markRequiresRefund($payment, __('booking.messages.seat_released_before_payment_finalization'));
                }
                $heldUntil = $seat->getRawOriginal('held_until');

                if ($heldUntil === null || BookingClock::parseStored((string) $heldUntil)?->lessThanOrEqualTo(BookingClock::now()) !== false) {
                    $this->expireAndReleaseBooking($booking);

                    return $this->markRequiresRefund($payment, __('booking.messages.seat_hold_expired_before_payment_finalization'));
                }
            }

            $wasPending = $bookingStatus->isPayable();
            if ($wasPending) {
                $booking->expires_at = null;
                app(TransitionBooking::class)->execute($booking, BookingStatus::Confirmed);
            }

            foreach ($items as $item) {
                $seat = $seats->get($item->getAttribute('screening_seat_id'));

                if ($seat === null) {
                    continue;
                }

                $seat->forceFill([
                    'status' => ScreeningSeatStatus::Sold,
                    'held_until' => null,
                    'hold_token' => null,
                    'held_by_booking_id' => null,
                    'sold_at' => BookingClock::now(),
                ])->save();

                if (str_starts_with((string) $item->getAttribute('ticket_code'), 'HOLD-')) {
                    $item->setAttribute('ticket_code', strtoupper('TKT-'.Str::random(20)));
                }

                $item->setAttribute('status', TicketStatus::Issued);
                $item->setAttribute('qr_token_hash', hash('sha256', (string) $item->getAttribute('ticket_code')));

                $item->save();
            }

            if ($wasPending) {
                $redeemedReservations = CouponReservation::query()
                    ->where('booking_id', $booking->getKey())
                    ->where('status', CouponReservationStatus::Reserved)
                    ->lockForUpdate()
                    ->get();
                $coupons = Coupon::query()
                    ->whereIn('id', $redeemedReservations->pluck('coupon_id')->unique()->values())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($redeemedReservations as $reservation) {
                    $reservation->update(['status' => CouponReservationStatus::Redeemed]);
                    $coupon = $coupons->get($reservation->coupon_id);
                    $coupon?->increment('redeemed_count');
                    $coupon?->decrement('reserved_count');
                    CouponUserUsage::query()
                        ->where('coupon_id', $reservation->coupon_id)
                        ->where('booking_id', $booking->getKey())
                        ->where('status', CouponReservationStatus::Reserved)
                        ->update(['status' => CouponReservationStatus::Redeemed]);
                }

                OutboxMessage::query()->create([
                    'aggregate_type' => Booking::class,
                    'aggregate_id' => $booking->getKey(),
                    'event_type' => OutboxEventType::BookingPaymentSucceeded,
                    'payload' => [
                        'booking_id' => $booking->getKey(),
                        'payment_id' => $payment->getKey(),
                        'locale' => app()->getLocale(),
                    ],
                ]);
            }

            return $payment->refresh();
        }, 3);
    }

    private function bookingCannotBeFinalized(Booking $booking, BookingStatus $status): bool
    {
        if ($status->isClosed()) {
            return true;
        }

        if (! $status->isPayable()) {
            return false;
        }

        $booking->loadMissing('screening');
        if (! $booking->screening?->isBookable()) {
            return true;
        }

        $expiresAt = $booking->getRawOriginal('expires_at');

        return $expiresAt === null
            || BookingClock::parseStored((string) $expiresAt)?->lessThanOrEqualTo(BookingClock::now()) !== false;
    }

    private function releaseBookingResources(Booking $booking): void
    {
        $this->resourceReleaser->execute($booking);
    }

    private function expireAndReleaseBooking(Booking $booking): void
    {
        if (BookingStatus::from((string) $booking->getRawOriginal('status'))->isPayable()) {
            app(TransitionBooking::class)->execute($booking, BookingStatus::Expired);

            $this->releaseBookingResources($booking);
        }
    }

    private function markRequiresRefund(Payment $payment, string $reason): Payment
    {
        $currentStatus = PaymentStatus::from((string) $payment->getRawOriginal('status'));
        if (! $this->stateMachine->canTransition($currentStatus, PaymentStatus::RequiresRefund)) {
            return $payment->refresh();
        }

        $metadata = $payment->getAttribute('metadata');

        $payment->setAttribute('status', PaymentStatus::RequiresRefund);
        $payment->setAttribute('failure_message', $reason);
        $payment->setAttribute('metadata', array_merge(is_array($metadata) ? $metadata : [], [
            'requires_refund' => true,
            'requires_refund_reason' => 'seat_not_available_during_finalization',
        ]));

        $payment->save();

        return $payment->refresh();
    }
}
