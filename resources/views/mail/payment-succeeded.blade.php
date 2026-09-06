<x-mail::message>
# {{ __('booking.mail.payment_title') }}

{{ __('booking.mail.payment_body', ['id' => $booking->id]) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
