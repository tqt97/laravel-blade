<?php

namespace App\Actions\Movie\Booking;

use App\Actions\Movie\Concessions\AddConcessions;
use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Models\Movie\Screening;
use App\Models\User;
use App\Support\Booking\Exceptions\BookingOperationFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EditBookingSelection
{
    public function __construct(
        private readonly HoldSeats $holdSeats,
        private readonly CancelBooking $cancelBooking,
        private readonly AddConcessions $addConcessions,
    ) {}

    /**
     * Reconcile the user's current selection with the existing hold atomically.
     *
     * The booking row is locked before comparing seats. A same-seat edit keeps
     * the aggregate and only synchronizes combos; a changed selection releases
     * the old resources before creating the replacement hold in the same retryable
     * transaction.
     *
     * @param  list<int|string>  $seatIds
     * @param  array<int|string, int|string>  $quantities
     */
    public function execute(User $user, Screening $screening, array $seatIds, string $idempotencyKey, array $quantities = []): Booking
    {
        return DB::transaction(function () use ($user, $screening, $seatIds, $idempotencyKey, $quantities): Booking {
            $activeHold = Booking::query()
                ->where('user_id', $user->getKey())
                ->where('screening_id', $screening->getKey())
                ->activeHold()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeHold?->getRawOriginal('status') === BookingStatus::PendingPayment->value) {
                throw new BookingOperationFailed(__('booking.messages.combos_locked'));
            }

            $requestedSeatIds = collect($seatIds)->map(fn (int|string $seatId): int => (int) $seatId)->sort()->values()->all();

            $heldSeatIds = $activeHold?->items()->with('screeningSeat')->get()->pluck('screeningSeat.seat_id')->map(fn (int|string $seatId): int => (int) $seatId)->sort()->values()->all() ?? [];

            if ($activeHold !== null && $requestedSeatIds === $heldSeatIds) {
                if ($quantities !== []) {
                    $this->addConcessions->executeForLockedBooking($activeHold, $quantities);
                }

                return $activeHold->refresh();
            }

            if ($activeHold === null) {
                $expiredHold = Booking::query()
                    ->where('user_id', $user->getKey())
                    ->where('screening_id', $screening->getKey())
                    ->expiredHold()
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($expiredHold !== null) {
                    $this->cancelBooking->execute($expiredHold, 'expired_hold_replaced');
                    $idempotencyKey = (string) Str::uuid();
                }
            }

            if ($activeHold !== null) {
                $this->cancelBooking->execute($activeHold, 'seat_selection_edited');
                $idempotencyKey = (string) Str::uuid();
            }

            $booking = $this->holdSeats->execute($user, $screening, $seatIds, $idempotencyKey);
            if ($quantities !== []) {
                $this->addConcessions->executeForLockedBooking($booking, $quantities);
            }

            return $booking->refresh();
        }, 3);
    }
}
