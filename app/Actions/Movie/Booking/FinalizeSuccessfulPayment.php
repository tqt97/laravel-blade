<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Infrastructure\OutboxEventType;
use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Booking\CouponReservationStatus;
use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Infrastructure\OutboxMessage;
use App\Models\Movie\Booking;
use App\Models\Movie\CouponReservation;
use App\Models\Movie\ScreeningSeat;
use App\Models\Payments\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FinalizeSuccessfulPayment
{
    public function __construct(private readonly ReleaseBookingResources $resourceReleaser) {}

    public function execute(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($payment->getRawOriginal('status') !== PaymentStatus::Succeeded->value) {
                return $payment;
            }

            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();
            $bookingStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));

            if (in_array($bookingStatus, [BookingStatus::Confirmed, BookingStatus::Completed], true)
                && ! $booking->items()->where('ticket_code', 'like', 'HOLD-%')->exists()) {
                return $payment;
            }

            if ($this->bookingCannotBeFinalized($booking, $bookingStatus)) {
                if (in_array($bookingStatus, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
                    $this->expireAndReleaseBooking($booking);
                }

                $metadata = $payment->getAttribute('metadata');
                $payment->setAttribute('status', PaymentStatus::RequiresRefund);
                $payment->setAttribute('failure_message', 'Payment succeeded after the booking hold expired.');
                $payment->setAttribute('metadata', array_merge(is_array($metadata) ? $metadata : [], [
                    'requires_refund' => true,
                    'requires_refund_reason' => 'booking_expired_before_finalization',
                ]));
                $payment->save();

                return $payment->refresh();
            }

            $items = $booking->items()->lockForUpdate()->get();
            $seats = [];
            foreach ($items as $item) {
                $seat = ScreeningSeat::query()->whereKey($item->getAttribute('screening_seat_id'))->lockForUpdate()->firstOrFail();
                $seats[] = [$item, $seat];
            }

            foreach ($seats as [, $seat]) {
                if ($seat->getAttribute('status') !== ScreeningSeatStatus::Held || (int) $seat->getAttribute('held_by_booking_id') !== $booking->getKey()) {
                    $this->expireAndReleaseBooking($booking);

                    return $this->markRequiresRefund($payment, 'A booking seat was released before payment finalization.');
                }
                $heldUntil = $seat->getRawOriginal('held_until');
                if ($heldUntil === null || CarbonImmutable::parse((string) $heldUntil, 'UTC')->lessThanOrEqualTo(now()->utc())) {
                    $this->expireAndReleaseBooking($booking);

                    return $this->markRequiresRefund($payment, 'A booking seat hold expired before payment finalization.');
                }
            }

            $wasPending = in_array($bookingStatus, [BookingStatus::Held, BookingStatus::PendingPayment], true);
            if ($wasPending) {
                $booking->transitionTo(BookingStatus::Confirmed);
                $booking->expires_at = null;
                $booking->save();
            }

            foreach ($seats as [$item, $seat]) {
                $seat->forceFill([
                    'status' => ScreeningSeatStatus::Sold,
                    'held_until' => null,
                    'hold_token' => null,
                    'held_by_booking_id' => null,
                    'sold_at' => now()->utc(),
                ])->save();

                if (str_starts_with((string) $item->getAttribute('ticket_code'), 'HOLD-')) {
                    $item->setAttribute('ticket_code', strtoupper('TKT-'.Str::random(20)));
                }
                $item->setAttribute('status', TicketStatus::Issued);
                $item->setAttribute('qr_token_hash', hash('sha256', (string) $item->getAttribute('ticket_code')));
                $item->save();
            }

            if ($wasPending) {
                CouponReservation::query()
                    ->where('booking_id', $booking->getKey())
                    ->where('status', CouponReservationStatus::Reserved)
                    ->update(['status' => CouponReservationStatus::Redeemed]);
                OutboxMessage::query()->create([
                    'aggregate_type' => Booking::class,
                    'aggregate_id' => $booking->getKey(),
                    'event_type' => OutboxEventType::BookingPaymentSucceeded,
                    'payload' => ['booking_id' => $booking->getKey(), 'payment_id' => $payment->getKey()],
                ]);
            }

            return $payment->refresh();
        }, 3);
    }

    private function bookingCannotBeFinalized(Booking $booking, BookingStatus $status): bool
    {
        if (in_array($status, [BookingStatus::Expired, BookingStatus::Cancelled, BookingStatus::NoShow], true)) {
            return true;
        }

        if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
            return false;
        }

        $expiresAt = $booking->getRawOriginal('expires_at');

        return $expiresAt === null || CarbonImmutable::parse((string) $expiresAt, 'UTC')->lessThanOrEqualTo(now()->utc());
    }

    private function releaseBookingResources(Booking $booking): void
    {
        $this->resourceReleaser->execute($booking);
    }

    private function expireAndReleaseBooking(Booking $booking): void
    {
        if (in_array(BookingStatus::from((string) $booking->getRawOriginal('status')), [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
            $booking->transitionTo(BookingStatus::Expired);
            $booking->save();
            $this->releaseBookingResources($booking);
        }
    }

    private function markRequiresRefund(Payment $payment, string $reason): Payment
    {
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
