<?php

namespace App\Booking\Actions;

use App\Booking\Data\CreateBookingData;
use App\Booking\Enums\BookingStatus;
use App\Booking\Exceptions\BookingConflict;
use App\Booking\Exceptions\IdempotencyConflict;
use App\Booking\Models\Booking;
use App\Booking\Queries\BookingAvailabilityQuery;
use App\Models\BookableResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateBooking
{
    public function __construct(private readonly BookingAvailabilityQuery $availability) {}

    public function execute(User $user, CreateBookingData $data): Booking
    {
        /**
         * The resource row is the serialization boundary. A Redis lock would
         * not protect this invariant from a second database writer.
         */
        return DB::transaction(function () use ($user, $data): Booking {
            $earliest = now()->utc()->addMinutes(config('booking.minimum_lead_minutes'));
            $latest = now()->utc()->addDays(config('booking.maximum_horizon_days'));

            if ($data->period->startAt->lessThan($earliest) || $data->period->endAt->greaterThan($latest)) {
                throw new InvalidArgumentException(__('booking.validation.period'));
            }

            $hash = hash('sha256', implode('|', [
                $data->resourceId,
                $data->period->startAt->toIso8601String(),
                $data->period->endAt->toIso8601String(),
            ]));

            if ($data->idempotencyKey !== null) {
                $existing = Booking::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $data->idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ($existing->idempotency_hash !== $hash) {
                        throw new IdempotencyConflict('The idempotency key was already used for another booking.');
                    }

                    return $existing;
                }
            }

            $resource = BookableResource::query()
                ->whereKey($data->resourceId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->availability->hasConflict($resource->id, $data->period)) {
                throw new BookingConflict('The selected period is no longer available.');
            }

            return Booking::query()->create([
                'user_id' => $user->id,
                'resource_id' => $resource->id,
                'status' => BookingStatus::Held,
                'start_at' => $data->period->startAt,
                'end_at' => $data->period->endAt,
                'expires_at' => now()->addMinutes(config('booking.hold_minutes')),
                'idempotency_key' => $data->idempotencyKey,
                'idempotency_hash' => $hash,
            ]);
        }, 3);
    }
}
