<x-layouts.movie :title="__('booking.success.title')">
    <div class="mx-auto max-w-3xl space-y-8 px-5 py-12 sm:px-8">
        @php
            $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
            $seatTotal = (int) $booking->items->sum('price_minor_units');
            $comboTotal = (int) $booking->concessions->sum('total_minor_units');
            $timezone = config('app.timezone');
        @endphp
        <section class="rounded-2xl border border-success/30 bg-success-soft p-6 text-center sm:p-10">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-success text-success-foreground">
                <span class="text-2xl font-bold" aria-hidden="true">✓</span>
            </div>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight">{{ __('booking.success.title') }}</h1>
            <p class="mx-auto mt-2 max-w-xl text-sm text-success-foreground">{{ __('booking.success.description') }}</p>
            <p class="mt-4 text-sm font-semibold text-success-foreground">
                {{ __('booking.bookings.booking_id', ['id' => $booking->id]) }}</p>
        </section>

        <section class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                    <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                </div>
                <span
                    class="rounded-full border border-success/30 bg-success-soft px-3 py-1 text-xs font-semibold text-success-foreground">
                    {{ __('booking.status.confirmed') }}
                </span>
            </div>

            <div class="mt-6 grid gap-4 rounded-xl bg-muted p-4 text-left sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.date') }}</p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ $booking->screening?->starts_at?->timezone($timezone)->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.schedule') }}</p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ $booking->screening?->starts_at?->timezone($timezone)->format('H:i') ?? '—' }} –
                        {{ $booking->screening?->ends_at?->timezone($timezone)->format('H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.room') }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ $booking->screening?->room?->name ?? '—' }}</p>
                </div>
            </div>

            <div class="mt-6 border-t border-border pt-6">
                <h3 class="text-sm font-semibold">{{ __('booking.success.summary') }}</h3>
                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('booking.bookings.seats') }}</dt>
                        <dd class="font-semibold">
                            {{ \App\Support\Money\Money::fromMinorUnits($seatTotal, $currency)->format() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">{{ __('booking.bookings.combos') }}</dt>
                        <dd class="font-semibold">
                            {{ \App\Support\Money\Money::fromMinorUnits($comboTotal, $currency)->format() }}</dd>
                    </div>
                    @if ((int) $booking->discount_minor_units > 0)
                        <div class="flex justify-between gap-4 text-success">
                            <dt>{{ __('booking.checkout.discount') }}</dt>
                            <dd>-{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->discount_minor_units, $currency)->format() }}
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4 border-t border-border pt-3 text-base">
                        <dt class="font-semibold">{{ __('booking.bookings.total') }}</dt>
                        <dd class="font-bold text-primary">
                            {{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, $currency)->format() }}
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($booking->concessions->isNotEmpty())
                <div class="mt-6 border-t border-border pt-6">
                    <h3 class="text-sm font-semibold">{{ __('booking.success.combos') }}</h3>
                    <div class="mt-3 space-y-2">
                        @foreach ($booking->concessions as $line)
                            <div class="flex items-center justify-between gap-4 rounded-lg bg-muted p-3 text-sm">
                                <span><span class="font-semibold">{{ $line->concession?->name ?? '—' }}</span><span
                                        class="ml-2 text-muted-foreground">× {{ $line->quantity }}</span></span><span
                                    class="font-semibold">{{ \App\Support\Money\Money::fromMinorUnits((int) $line->total_minor_units, $currency)->format() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($booking->items as $item)
                    <a href="{{ route('user.tickets.show', $item) }}"
                        class="group rounded-xl border border-border bg-muted p-4 transition hover:border-primary hover:bg-accent">
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-lg font-bold">{{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}</span>
                            <span
                                class="text-sm font-semibold text-primary group-hover:underline">{{ __('booking.success.view_ticket') }}
                                →</span>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">{{ $item->ticket_code }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                <x-admin.button :href="route('user.bookings.show', $booking)" variant="secondary" icon="eye">
                    {{ __('booking.success.view_details') }}
                </x-admin.button>
                <x-admin.button :href="route('user.bookings.index')" variant="ghost">
                    {{ __('booking.bookings.title') }}
                </x-admin.button>
            </div>
        </section>
    </div>
</x-layouts.movie>
