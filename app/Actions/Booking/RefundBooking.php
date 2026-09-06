<?php

namespace App\Actions\Booking;

use App\Contracts\PaymentGateway;
use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\ScreeningSeat;
use App\Models\Payments\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RefundBooking
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function execute(Booking $booking): Payment
    {
        $payment = Payment::query()->where('payable_type', Booking::class)->where('payable_id', $booking->getKey())->firstOrFail();
        if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Refunded) {
            return $payment;
        }
        if (PaymentStatus::from((string) $payment->getRawOriginal('status')) !== PaymentStatus::Succeeded) {
            throw new RuntimeException('Only successful payments can be refunded.');
        }
        if ($booking->items()->where('status', TicketStatus::CheckedIn)->exists()) {
            throw new RuntimeException('A checked-in ticket cannot be refunded.');
        }
        $result = $this->gateway->refund($payment);
        if ($result->status !== 'refunded') {
            throw new RuntimeException($result->failureMessage ?? 'Refund failed.');
        }

        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (PaymentStatus::from((string) $payment->getRawOriginal('status')) === PaymentStatus::Refunded) {
                return $payment;
            }
            $payment->setAttribute('status', PaymentStatus::Refunded);
            $payment->setAttribute('refunded_at', now()->utc());
            $payment->setAttribute('metadata', $result->metadata);
            $payment->save();
            $booking = Booking::query()->whereKey($payment->getAttribute('payable_id'))->lockForUpdate()->firstOrFail();
            foreach ($booking->items()->lockForUpdate()->get() as $item) {
                $seat = ScreeningSeat::query()->whereKey($item->getAttribute('screening_seat_id'))->lockForUpdate()->first();
                if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Sold) {
                    $seat->forceFill(['status' => ScreeningSeatStatus::Available, 'sold_at' => null])->save();
                }
                $item->setAttribute('status', TicketStatus::Refunded);
                $item->save();
            }
            foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
                $concession = Concession::query()->find($line->getAttribute('concession_id'));
                if ($concession !== null && $concession->getAttribute('stock') !== null) {
                    $concession->increment('stock', (int) $line->getAttribute('quantity'));
                }
            }
            $bookingStatus = BookingStatus::from((string) $booking->getRawOriginal('status'));
            if ($bookingStatus->canTransitionTo(BookingStatus::Cancelled)) {
                $booking->transitionTo(BookingStatus::Cancelled);
                $booking->setAttribute('cancellation_reason', 'Payment refunded');
                $booking->save();
            }

            return $payment->refresh();
        }, 3);
    }
}
