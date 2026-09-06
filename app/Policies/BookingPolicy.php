<?php

namespace App\Policies;

use App\Enums\Booking\BookingStatus;
use App\Models\Cinema\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;

final class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->is_admin || $booking->user_id === $user->id;
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($booking->user_id !== $user->id) {
            return false;
        }

        $status = BookingStatus::tryFrom((string) $booking->getRawOriginal('status'));
        if ($status === BookingStatus::Cancelled) {
            return true;
        }

        if (! in_array($status, [BookingStatus::Held, BookingStatus::PendingPayment], true)) {
            return false;
        }

        $deadlineMinutes = (int) config('booking.cancellation_deadline_minutes');
        if ($deadlineMinutes <= 0) {
            return true;
        }

        $booking->loadMissing('screening');
        $rawStartAt = $booking->screening?->getRawOriginal('starts_at');
        if ($rawStartAt === null) {
            return false;
        }

        return CarbonImmutable::parse((string) $rawStartAt, 'UTC')->isAfter(now()->utc()->addMinutes($deadlineMinutes));
    }
}
