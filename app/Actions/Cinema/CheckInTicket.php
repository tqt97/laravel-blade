<?php

namespace App\Actions\Cinema;

use App\Enums\Booking\BookingStatus;
use App\Enums\Cinema\TicketStatus;
use App\Models\Cinema\BookingItem;
use App\Models\Cinema\Screening;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckInTicket
{
    public function execute(string $ticketCode, int $staffId): BookingItem
    {
        return DB::transaction(function () use ($ticketCode, $staffId): BookingItem {
            $item = BookingItem::query()->where('ticket_code', $ticketCode)->lockForUpdate()->firstOrFail();
            if ($item->getAttribute('status') !== TicketStatus::Issued) {
                throw new RuntimeException('This ticket is not valid for check-in.');
            }
            $booking = $item->booking()->firstOrFail();
            if ($booking->getAttribute('status') !== BookingStatus::Confirmed) {
                throw new RuntimeException('The booking is not confirmed.');
            }
            $screening = Screening::query()->find($booking->getAttribute('screening_id'));
            $startsAt = $screening !== null && $screening->getRawOriginal('starts_at') !== null
                ? CarbonImmutable::parse((string) $screening->getRawOriginal('starts_at'), 'UTC')
                : null;
            $endsAt = $screening !== null && $screening->getRawOriginal('ends_at') !== null
                ? CarbonImmutable::parse((string) $screening->getRawOriginal('ends_at'), 'UTC')
                : null;
            if ($startsAt === null || $endsAt === null || now()->utc()->lessThan($startsAt->subMinutes((int) config('booking.check_in_open_minutes'))) || now()->utc()->greaterThan($endsAt)) {
                throw new RuntimeException('Check-in is not open for this screening.');
            }
            $item->setAttribute('status', TicketStatus::CheckedIn);
            $item->setAttribute('checked_in_at', now()->utc());
            $item->setAttribute('checked_in_by', $staffId);
            $item->save();

            return $item->refresh();
        }, 3);
    }
}
