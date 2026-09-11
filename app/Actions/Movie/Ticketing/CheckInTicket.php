<?php

namespace App\Actions\Movie\Ticketing;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Movie\BookingItem;
use App\Models\Movie\Screening;
use App\Models\Payments\RefundAttempt;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;

final class CheckInTicket
{
    public function execute(string $ticketCode, int $staffId): BookingItem
    {
        return DB::transaction(function () use ($ticketCode, $staffId): BookingItem {
            $item = BookingItem::query()->where('ticket_code', $ticketCode)->first();
            if ($item === null) {
                throw new BookingOperationFailed(__('booking.messages.ticket_not_found'));
            }

            $booking = $item->booking()->lockForUpdate()->firstOrFail();
            $payment = $booking->payment()->lockForUpdate()->first();
            $item = BookingItem::query()
                ->whereKey($item->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($item->getAttribute('status') !== TicketStatus::Issued) {
                throw new BookingOperationFailed(__('booking.messages.ticket_invalid_check_in'));
            }

            if ($booking->getAttribute('status') !== BookingStatus::Confirmed) {
                throw new BookingOperationFailed(__('booking.messages.booking_not_confirmed'));
            }

            if (
                $payment?->getRawOriginal('status') === PaymentStatus::Refunding->value
                || ($payment !== null && RefundAttempt::query()->where('payment_id', $payment->getKey())->open()->exists())
            ) {
                throw new BookingOperationFailed(__('booking.messages.refund_in_progress'));
            }

            $screening = Screening::query()->find($booking->getAttribute('screening_id'));
            $startsAt = $screening !== null && $screening->getRawOriginal('starts_at') !== null
                ? BookingClock::parseStored((string) $screening->getRawOriginal('starts_at'))
                : null;
            $endsAt = $screening !== null && $screening->getRawOriginal('ends_at') !== null
                ? BookingClock::parseStored((string) $screening->getRawOriginal('ends_at'))
                : null;

            if (
                $startsAt === null ||
                $endsAt === null ||
                BookingClock::now()->lessThan($startsAt->subMinutes((int) config('booking.check_in_open_minutes'))) ||
                BookingClock::now()->greaterThan($endsAt)
            ) {
                throw new BookingOperationFailed(__('booking.messages.check_in_closed'));
            }

            $item->setAttribute('status', TicketStatus::CheckedIn);
            $item->setAttribute('checked_in_at', now());
            $item->setAttribute('checked_in_by', $staffId);

            $item->save();

            return $item->refresh();
        }, 3);
    }
}
