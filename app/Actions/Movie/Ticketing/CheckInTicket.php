<?php

namespace App\Actions\Movie\Ticketing;

use App\Enums\Movie\Booking\BookingStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Enums\Payment\RefundAttemptStatus;
use App\Models\Movie\BookingItem;
use App\Models\Movie\Screening;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Carbon\CarbonImmutable;
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
            $item = BookingItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($item->getAttribute('status') !== TicketStatus::Issued) {
                throw new BookingOperationFailed(__('booking.messages.ticket_invalid_check_in'));
            }

            if ($booking->getAttribute('status') !== BookingStatus::Confirmed) {
                throw new BookingOperationFailed(__('booking.messages.booking_not_confirmed'));
            }

            if ($booking->payment?->refundAttempts()->whereIn('status', [RefundAttemptStatus::Processing, RefundAttemptStatus::Unknown])->exists()) {
                throw new BookingOperationFailed(__('booking.messages.refund_in_progress'));
            }

            $screening = Screening::query()->find($booking->getAttribute('screening_id'));
            $startsAt = $screening !== null && $screening->getRawOriginal('starts_at') !== null
                ? CarbonImmutable::parse((string) $screening->getRawOriginal('starts_at'), 'UTC')
                : null;
            $endsAt = $screening !== null && $screening->getRawOriginal('ends_at') !== null
                ? CarbonImmutable::parse((string) $screening->getRawOriginal('ends_at'), 'UTC')
                : null;

            if ($startsAt === null || $endsAt === null || now()->utc()->lessThan($startsAt->subMinutes((int) config('booking.check_in_open_minutes'))) || now()->utc()->greaterThan($endsAt)) {
                throw new BookingOperationFailed(__('booking.messages.check_in_closed'));
            }

            $item->setAttribute('status', TicketStatus::CheckedIn);
            $item->setAttribute('checked_in_at', now()->utc());
            $item->setAttribute('checked_in_by', $staffId);
            $item->save();

            return $item->refresh();
        }, 3);
    }
}
