<?php

namespace App\Actions\Booking;

use App\Enums\Cinema\ScreeningSeatStatus;
use App\Enums\Cinema\TicketStatus;
use App\Models\Cinema\Booking;
use App\Models\Cinema\Concession;
use App\Models\Cinema\ScreeningSeat;

final class ReleaseBookingResources
{
    /**
     * Release seats, cancel provisional tickets and return combo stock.
     * The caller must already be inside a database transaction.
     */
    public function execute(Booking $booking): void
    {
        foreach ($booking->items()->lockForUpdate()->get() as $item) {
            $seat = ScreeningSeat::query()
                ->whereKey($item->getAttribute('screening_seat_id'))
                ->lockForUpdate()
                ->first();

            if ($seat !== null && $seat->getAttribute('status') === ScreeningSeatStatus::Held) {
                $seat->forceFill([
                    'status' => ScreeningSeatStatus::Available,
                    'hold_token' => null,
                    'held_until' => null,
                ])->save();
            }

            if ($item->getAttribute('status') !== TicketStatus::Cancelled) {
                $item->setAttribute('status', TicketStatus::Cancelled);
                $item->save();
            }
        }

        foreach ($booking->concessions()->lockForUpdate()->get() as $line) {
            $concession = Concession::query()
                ->whereKey($line->getAttribute('concession_id'))
                ->lockForUpdate()
                ->first();

            if ($concession !== null && $concession->getAttribute('stock') !== null) {
                $concession->increment('stock', (int) $line->getAttribute('quantity'));
            }
        }
    }
}
