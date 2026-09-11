<?php

namespace App\Policies\Movie;

use App\Enums\Movie\Booking\BookingStatus;
use App\Models\Movie\Booking;
use App\Models\User;
use App\Support\Time\BookingClock;

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

    public function pay(User $user, Booking $booking): bool
    {
        return $this->confirm($user, $booking)
            && BookingStatus::tryFrom((string) $booking->getRawOriginal('status'))?->isPayable() === true;
    }

    public function editSelection(User $user, Booking $booking): bool
    {
        return $this->confirm($user, $booking)
            && BookingStatus::tryFrom((string) $booking->getRawOriginal('status')) === BookingStatus::Held;
    }

    public function changeCombos(User $user, Booking $booking): bool
    {
        return $this->editSelection($user, $booking);
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

        if (! $status->isPayable()) {
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

        return BookingClock::parseStored((string) $rawStartAt)?->isAfter(BookingClock::now()->addMinutes($deadlineMinutes)) ?? false;
    }

    public function refund(User $user, Booking $booking): bool
    {
        return $user->is_admin && $booking->payment()->exists();
    }
}
