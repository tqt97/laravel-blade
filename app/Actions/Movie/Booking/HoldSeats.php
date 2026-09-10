<?php

namespace App\Actions\Movie\Booking;

use App\Enums\Movie\Seating\ScreeningSeatStatus;
use App\Enums\Movie\Ticketing\TicketStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Screening;
use App\Models\Movie\ScreeningSeat;
use App\Models\User;
use App\Support\Booking\SeatHoldConflict;
use App\Support\Time\BookingClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class HoldSeats
{
    /**
     * @param  list<int>  $seatIds
     */
    public function execute(User $user, Screening $screening, array $seatIds, string $idempotencyKey): Booking
    {
        $seatIds = array_values(array_unique(array_map(static fn (int|string $id): int => (int) $id, $seatIds)));
        sort($seatIds);
        if ($seatIds === []) {
            throw new SeatHoldConflict(__('booking.messages.select_seat'));
        }

        return DB::transaction(function () use ($user, $screening, $seatIds, $idempotencyKey): Booking {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $screening->id.'|'.implode(',', $seatIds));
            $existing = Booking::query()->where('user_id', $user->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->getAttribute('idempotency_hash') !== $hash) {
                    throw new SeatHoldConflict(__('booking.messages.idempotency_key_reused'));
                }

                return $existing;
            }

            $screening = Screening::query()->whereKey($screening->id)->lockForUpdate()->firstOrFail();
            if (! $screening->isBookable()) {
                throw new SeatHoldConflict(__('booking.messages.screening_outside_window'));
            }

            $rows = ScreeningSeat::query()->where('screening_id', $screening->id)->whereIn('seat_id', $seatIds)->orderBy('seat_id')->lockForUpdate()->get();
            if ($rows->count() !== count($seatIds)) {
                throw new SeatHoldConflict(__('booking.messages.seats_not_in_screening'));
            }
            $now = BookingClock::now();
            foreach ($rows as $row) {
                if ($row->getAttribute('status') === ScreeningSeatStatus::Held && $row->getAttribute('held_until')?->lessThanOrEqualTo($now)) {
                    $row->forceFill(['status' => ScreeningSeatStatus::Available, 'hold_token' => null, 'held_by_booking_id' => null, 'held_until' => null])->save();
                }
                if ($row->getAttribute('status') !== ScreeningSeatStatus::Available) {
                    throw new SeatHoldConflict(__('booking.validation.seats_unavailable'));
                }
            }
            $expiresAt = $now->addMinutes((int) config('booking.limits.hold_minutes'));
            $token = (string) Str::uuid();
            $total = (int) $rows->sum(fn (ScreeningSeat $row): int => (int) $row->getAttribute('price_minor_units'));
            $booking = Booking::createHeld([
                'user_id' => $user->id, 'screening_id' => $screening->id, 'expires_at' => $expiresAt,
                'idempotency_key' => $idempotencyKey, 'idempotency_hash' => $hash,
                'amount_minor_units' => $total, 'currency' => $screening->getAttribute('currency'),
                'subtotal_minor_units' => $total, 'discount_minor_units' => 0, 'total_minor_units' => $total,
                'pricing_currency' => $screening->getAttribute('currency'),
            ]);
            foreach ($rows as $row) {
                $row->forceFill(['status' => ScreeningSeatStatus::Held, 'hold_token' => $token, 'held_by_booking_id' => $booking->getKey(), 'held_until' => $expiresAt])->save();
                $booking->items()->create(['screening_seat_id' => $row->id, 'ticket_code' => 'HOLD-'.$booking->id.'-'.$row->seat_id, 'price_minor_units' => $row->getAttribute('price_minor_units'), 'currency' => $row->getAttribute('currency'), 'status' => TicketStatus::Issued]);
            }

            return $booking->load(['items.screeningSeat.seat', 'screening.movie', 'screening.room']);
        }, 3);
    }
}
