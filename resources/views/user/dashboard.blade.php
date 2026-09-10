<x-layouts.user :title="__('booking.nav.dashboard')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div>
            <p class="text-sm font-semibold text-primary">{{ __('booking.nav.group') }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                {{ __('booking.dashboard.welcome', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('booking.dashboard.description') }}
            </p>
        </div>
        @if ($upcomingBooking)
            <section class="rounded-2xl border border-primary/25 bg-primary-soft/40 p-5 shadow-sm sm:p-6"
                data-booking-checkout data-countdown-mode="showtime"
                data-day-label="{{ __('booking.dashboard.day_unit') }}"
                data-hour-label="{{ __('booking.dashboard.hour_unit') }}"
                data-minute-label="{{ __('booking.dashboard.minute_unit') }}"
                data-expires-at="{{ optional($upcomingBooking->screening?->starts_at)->timezone(config('app.timezone'))->toIso8601String() }}">

                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-primary">
                            {{ __('booking.dashboard.upcoming_title') }}</p>
                        <h2 class="mt-2 text-xl font-semibold">
                            {{ $upcomingBooking->screening?->movie?->title ?? '—' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $upcomingBooking->screening?->room?->name ?? '—' }} ·
                            {{ $upcomingBooking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </p>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ __('booking.bookings.seats') }}:
                            {{ $upcomingBooking->items_count }} · {{ __('booking.bookings.combos') }}:
                            {{ $upcomingBooking->concessions_count }} ·
                            {{ \App\Support\Money\Money::fromMinorUnits((int) $upcomingBooking->total_minor_units, strtoupper((string) ($upcomingBooking->pricing_currency ?? config('booking.payment.currency'))))->format() }}
                        </p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-xs text-muted-foreground">
                            {{ __('booking.dashboard.starts_in') }}</p><strong data-booking-countdown
                            class="mt-1 block text-2xl tabular-nums text-primary">--:--</strong>
                        <a href="{{ route('user.bookings.show', $upcomingBooking) }}"
                            class="mt-2 inline-block text-sm font-semibold text-primary hover:underline">
                            {{ __('booking.bookings.details') }}
                            →
                        </a>
                    </div>
                </div>
            </section>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('cinema.movies.index') }}"
                class="rounded-2xl border border-border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50">

                <p class="text-sm font-semibold">{{ __('booking.dashboard.browse_title') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ __('booking.dashboard.browse_description') }}
                </p>
            </a>
            <a href="{{ route('user.bookings.index') }}"
                class="rounded-2xl border border-border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50">
                <p class="text-sm font-semibold">{{ __('booking.dashboard.history_title') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.dashboard.history_description') }}</p>
            </a>
        </div>
        @if ($recentBookings->isNotEmpty())
            <section>
                <h2 class="text-xl font-semibold">{{ __('booking.dashboard.recent_title') }}</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recentBookings as $recentBooking)
                        <a href="{{ route('user.bookings.show', $recentBooking) }}"
                            class="rounded-xl border border-border bg-card p-4 text-sm transition hover:border-primary"><span
                                class="font-semibold">{{ $recentBooking->screening?->movie?->title ?? '—' }}</span><span
                                class="mt-1 block text-muted-foreground">{{ $recentBooking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</span><span
                                class="mt-2 block text-xs text-muted-foreground">{{ __('booking.status.' . $recentBooking->status->value) }}
                                · {{ $recentBooking->items_count }} {{ __('booking.bookings.seats') }} ·
                                {{ $recentBooking->concessions_count }}
                                {{ __('booking.bookings.combos') }}</span><span
                                class="mt-2 block font-semibold text-primary">{{ \App\Support\Money\Money::fromMinorUnits((int) $recentBooking->total_minor_units, strtoupper((string) ($recentBooking->pricing_currency ?? config('booking.payment.currency'))))->format() }}</span></a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.user>
