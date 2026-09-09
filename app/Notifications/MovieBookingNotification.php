<?php

namespace App\Notifications;

use App\Models\Movie\Booking;
use Illuminate\Notifications\Notification;

final class MovieBookingNotification extends Notification
{
    public function __construct(
        private readonly Booking $booking,
        private readonly string $event,
    ) {}

    /**
     * Create a new notification instance.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'key' => $this->event.':'.$this->booking->getKey(),
            'event' => $this->event,
            'title' => __('booking.notifications.'.$this->event.'.title'),
            'message' => __('booking.notifications.'.$this->event.'.message', ['movie' => (string) ($this->booking->screening?->movie?->getAttribute('title') ?? 'Movie'), 'id' => $this->booking->getKey()]),
            'url' => route('user.bookings.show', $this->booking),
        ];
    }
}
