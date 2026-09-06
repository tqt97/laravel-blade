<x-layouts.user :title="__('cinema.tickets.title')">
    <div class="mx-auto max-w-lg space-y-6">
        <a href="{{ route('user.bookings.show', $ticket->booking) }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('booking.bookings.details') }}</a>
        <section class="rounded-2xl border border-border bg-card p-6 text-center shadow-sm sm:p-8">
            <p class="text-sm text-muted-foreground">{{ $ticket->booking->screening?->movie?->title }}</p>
            <h1 class="mt-2 text-2xl font-semibold">{{ $ticket->screeningSeat?->seat?->row_label }}{{ $ticket->screeningSeat?->seat?->seat_number }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ $ticket->booking->screening?->room?->name }} · {{ $ticket->booking->screening?->starts_at?->timezone($ticket->booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <div class="mx-auto mt-6 size-60 overflow-hidden rounded-xl bg-white p-2">{!! $qrCode !!}</div>
            <div class="mt-6 rounded-xl bg-muted p-4 text-left"><p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ __('cinema.tickets.qr_payload') }}</p><code class="mt-2 block break-all text-xs">{{ $verifyUrl }}</code></div>
            <p class="mt-4 text-xs text-muted-foreground">{{ __('cinema.tickets.qr_hint') }}</p>
        </section>
    </div>
</x-layouts.user>
