<x-layouts.user :title="__('booking.bookings.title')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.bookings.title') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.dashboard.history_description') }}</p>
            </div><x-admin.button :href="route('cinema.movies.index')"
                icon="arrow-right">{{ __('cinema.public.movies') }}</x-admin.button>
        </div>
        <form method="GET" class="flex flex-wrap items-center gap-3" aria-label="{{ __('booking.bookings.status') }}">
            <label for="booking-status" class="text-sm font-semibold">{{ __('booking.bookings.status') }}</label>
            <select id="booking-status" name="status" onchange="this.form.submit()" class="rounded-xl border border-border bg-card px-3 py-2 text-sm">
                <option value="">{{ __('booking.bookings.all_statuses') }}</option>
                @foreach (\App\Enums\Movie\Booking\BookingStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('booking.status.'.$status->value) }}</option>
                @endforeach
            </select>
        </form>
        @if ($bookings->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                {{ __('booking.bookings.empty') }}
            </div>
        @else
            <div class="grid gap-3 sm:hidden">
                @foreach ($bookings as $booking)
                    <a href="{{ route('user.bookings.show', $booking) }}" class="rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:border-primary">
                        <div class="flex items-start justify-between gap-3"><span class="font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</span><span class="text-xs font-semibold">{{ __('booking.status.'.$booking->status->value) }}</span></div>
                        <p class="mt-2 text-sm text-muted-foreground">{{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">{{ $booking->items_count }} {{ __('booking.bookings.seats') }} · {{ $booking->concessions_count }} {{ __('booking.bookings.combos') }}</p>
                        <p class="mt-2 text-sm font-semibold text-primary">{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, strtoupper((string) ($booking->pricing_currency ?? config('booking.payment.currency'))))->format() }}</p>
                        <p class="mt-3 text-sm font-semibold text-primary">{{ __('booking.bookings.details') }} →</p>
                    </a>
                @endforeach
            </div>
            <div class="hidden overflow-hidden rounded-2xl border border-border bg-card shadow-sm sm:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-sm">
                        <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-5 py-4">{{ __('booking.bookings.resource') }}</th>
                                <th class="px-5 py-4">{{ __('booking.bookings.period') }}</th>
                                <th class="whitespace-nowrap px-5 py-4">{{ __('booking.bookings.created') }}</th>
                                <th class="whitespace-nowrap px-5 py-4">{{ __('booking.bookings.expires') }}</th>
                                <th class="px-5 py-4">{{ __('booking.bookings.status') }}</th>
                                <th class="whitespace-nowrap px-5 py-4 text-right">{{ __('booking.bookings.total') }}</th>
                                <th class="whitespace-nowrap px-5 py-4 text-right">{{ __('booking.bookings.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($bookings as $booking)
                                <tr class="align-top">
                                    <td class="px-5 py-4 font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                        {{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}<br>{{ $booking->screening?->ends_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('H:i') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                        {{ $booking->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                        {{ $booking->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    @php
                                        $statusClasses = match ($booking->status->value) {
                                            \App\Enums\Movie\Booking\BookingStatus::Held->value, \App\Enums\Movie\Booking\BookingStatus::PendingPayment->value => 'border border-warning/30 bg-warning-soft text-warning-foreground',
                                            \App\Enums\Movie\Booking\BookingStatus::Confirmed->value, \App\Enums\Movie\Booking\BookingStatus::Completed->value => 'border border-success/30 bg-success-soft text-success-foreground',
                                            \App\Enums\Movie\Booking\BookingStatus::Cancelled->value, \App\Enums\Movie\Booking\BookingStatus::Expired->value, \App\Enums\Movie\Booking\BookingStatus::NoShow->value => 'border border-destructive/30 bg-destructive/10 text-destructive',
                                            default => 'border border-border bg-muted text-muted-foreground',
                                        };
                                    @endphp
                                    <td class="px-5 py-4"><span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}"><span
                                                class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ __('booking.status.' . $booking->status->value) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right font-semibold">{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, strtoupper((string) ($booking->pricing_currency ?? config('booking.payment.currency'))))->format() }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <x-admin.button :href="route('user.bookings.show', $booking)" variant="ghost" icon="eye"
                                            icon-only :title="__('booking.bookings.details')"
                                            aria-label="{{ __('booking.bookings.details') }}: {{ $booking->screening?->movie?->title ?? $booking->resource?->name ?? '—' }}" />
                                    </td>
                            </tr>@endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div>{{ $bookings->links() }}</div>
        @endif
    </div>
</x-layouts.user>
