<x-layouts.user :title="__('cinema.tickets.title')">
    <div class="mx-auto max-w-lg space-y-6">
        <a href="{{ route('user.bookings.show', $ticket->booking) }}"
            class="text-sm font-semibold text-primary hover:underline">
            ← {{ __('booking.bookings.details') }}
        </a>

        <section class="rounded-2xl border border-border bg-card p-6 text-center shadow-sm sm:p-8" data-ticket-card
            data-share-title="{{ $ticket->booking->screening?->movie?->title }}"
            data-calendar-title="{{ $ticket->booking->screening?->movie?->title }}"
            data-calendar-start="{{ $ticket->booking->screening?->starts_at?->timezone(config('app.timezone'))->toIso8601String() }}"
            data-calendar-end="{{ $ticket->booking->screening?->ends_at?->timezone(config('app.timezone'))->toIso8601String() }}"
            data-calendar-location="{{ $ticket->booking->screening?->room?->name }}">

            <p class="text-sm text-muted-foreground">{{ __('cinema.tickets.title') }}</p>
            <h1 class="mt-2 text-2xl font-semibold">{{ $ticket->booking->screening?->movie?->title }}</h1>
            <p class="mt-2 text-lg font-bold text-primary">{{ __('cinema.tickets.seat') }} {{ $ticket->screeningSeat?->seat?->row_label }}{{ $ticket->screeningSeat?->seat?->seat_number }}</p>
            <span class="mt-3 inline-flex rounded-full border border-success/30 bg-success-soft px-3 py-1 text-xs font-semibold text-success-foreground" role="status">
                {{ __('cinema.tickets.status_label') }}: {{ __('cinema.tickets.status.'.str_replace('-', '_', $ticket->getRawOriginal('status'))) }}
            </span>
            <p class="mt-2 text-sm text-muted-foreground">{{ $ticket->booking->screening?->room?->name }} ·
                {{ $ticket->booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            </p>
            <div class="mx-auto mt-6 size-60 overflow-hidden rounded-xl bg-white p-2">
                {!! $qrCode !!}
            </div>
            <details class="mt-6 rounded-xl border border-border bg-muted p-4 text-left">
                <summary class="cursor-pointer text-sm font-semibold">{{ __('cinema.tickets.show_secure_link') }}</summary>
                <div class="mt-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ __('cinema.tickets.qr_payload') }}
                </p>
                <code class="mt-2 block break-all text-xs">{{ $verifyUrl }}</code>
                </div>
            </details>
            <p class="mt-4 text-xs text-muted-foreground">{{ __('cinema.tickets.qr_hint') }}</p>

            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <button type="button" data-ticket-download
                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold transition hover:border-primary">
                    {{ __('cinema.tickets.download') }}
                </button>
                <button type="button" data-ticket-share
                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold transition hover:border-primary">
                    {{ __('cinema.tickets.share') }}
                </button>
                <button type="button" data-ticket-calendar
                    class="rounded-xl border border-border px-3 py-2 text-sm font-semibold transition hover:border-primary">
                    {{ __('cinema.tickets.calendar') }}
                </button>
            </div>
        </section>
    </div>
</x-layouts.user>
