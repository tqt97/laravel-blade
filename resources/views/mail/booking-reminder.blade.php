<x-mail::message>
# {{ __('booking.mail.reminder_title') }}

{{ __('booking.mail.reminder_body', ['movie' => $booking->screening?->movie?->title ?? 'Movie']) }}

{{ __('booking.mail.booking_id', ['id' => $booking->id]) }}

{{ __('booking.mail.reminder_action_hint') }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
