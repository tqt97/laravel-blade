<x-mail::message>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
        style="margin-bottom:24px;background:#172554;border-radius:16px;">
        <tr>
            <td style="padding:28px 24px;color:#ffffff;">
                <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#bfdbfe;">
                    {{ config('app.movie_name') }}</div>
                <h1 style="margin:8px 0 6px;font-size:26px;line-height:1.2;color:#ffffff;">
                    {{ __('booking.mail.confirmation_title') }}</h1>
                <div style="font-size:14px;color:#dbeafe;">
                    {{ __('booking.mail.confirmation_body', ['movie' => $booking->screening?->movie?->title ?? 'Movie']) }}
                </div>
            </td>
        </tr>
    </table>

    <x-mail::panel>
        **{{ __('booking.mail.movie') }}:** {{ $booking->screening?->movie?->title ?? '—' }}
        **{{ __('booking.mail.screening_time') }}:**
        {{ $booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
        **{{ __('booking.mail.room') }}:** {{ $booking->screening?->room?->name ?? '—' }}
        **{{ __('booking.mail.booking_id', ['id' => $booking->id]) }}**
    </x-mail::panel>

    ## {{ __('booking.mail.seats') }}

    {{ $booking->items->map(fn($item) => ($item->screeningSeat?->seat?->row_label ?? '') . ($item->screeningSeat?->seat?->seat_number ?? ''))->implode(', ') }}

    ## {{ __('booking.mail.tickets') }}

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            @foreach ($tickets as $ticket)
                <td width="{{ 100 / min(4, max(1, count($tickets))) }}%" valign="top" align="center"
                    style="padding:8px;">
                    <img src="{{ $message->embedData(base64_decode($ticket['qr_base64']), 'ticket-' . $loop->index . '.svg', 'image/svg+xml') }}"
                        width="180" height="180"
                        alt="{{ __('booking.mail.qr_alt', ['seat' => $ticket['seat']]) }}"
                        style="display:block;max-width:180px;width:100%;height:auto;margin:0 auto 8px;">
                    <strong>{{ __('booking.mail.seat') }} {{ $ticket['seat'] }}</strong><br>
                    <a href="{{ $ticket['verify_url'] }}">{{ __('booking.mail.view_ticket') }}</a>
                </td>
                @if ($loop->iteration % min(4, max(1, count($tickets))) === 0 && !$loop->last)
        </tr>
        <tr>
            @endif
            @endforeach
        </tr>
    </table>

    ## {{ __('booking.mail.summary') }}

    {{ __('booking.mail.seat_total') }}:
    **{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->items->sum('price_minor_units'), (string) $booking->currency)->format() }}**
    {{ __('booking.mail.combo_total') }}:
    **{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->concessions->sum('total_minor_units'), (string) $booking->currency)->format() }}**
    {{ __('booking.mail.total') }}:
    **{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, (string) $booking->currency)->format() }}**

    <x-mail::button :url="route('user.bookings.show', $booking)">
        {{ __('booking.mail.manage_booking') }}
    </x-mail::button>

    {{ __('booking.mail.manage_hint') }}

    {{ __('booking.mail.thanks') }},<br>
    {{ config('app.movie_name') }}
</x-mail::message>
