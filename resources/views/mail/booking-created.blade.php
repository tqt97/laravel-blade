<x-mail::message>
# {{ __('booking.mail.created_title') }}

{{ __('booking.mail.created_body', ['movie' => $booking->screening?->movie?->title ?? 'movie']) }}

{{ __('booking.mail.booking_id', ['id' => $booking->id]) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
