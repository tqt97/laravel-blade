<x-layouts.user :title="__('booking.bookings.details')">
    <div class="mx-auto max-w-3xl space-y-8">
        <div><a href="{{ route('user.bookings.index') }}" class="text-sm font-semibold text-primary hover:underline">←
                {{ __('booking.bookings.title') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.bookings.details') }}</h1>
        </div>

        <x-auth.feedback />

        @php
            $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
            $seatTotal = (int) $booking->items->sum('price_minor_units');
            $comboTotal = (int) $booking->concessions->sum('total_minor_units');
            $timezone = config('app.timezone');
        @endphp

        <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                    <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                </div>
                <span class="w-fit rounded-full bg-muted px-3 py-1 text-xs font-semibold">
                    {{ __('booking.status.' . $booking->status->value) }}
                </span>
            </div>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.start') }}
                    </dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->screening?->starts_at?->timezone($timezone)->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.end') }}
                    </dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->screening?->ends_at?->timezone($timezone)->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>
            </dl>
            @if ($booking->status === \App\Enums\Movie\Booking\BookingStatus::Held)
                <p class="rounded-xl bg-warning-soft p-4 text-sm text-warning-foreground">
                    {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.limits.hold_minutes')]) }}
                </p>
            @endif
            <div class="border-t border-border pt-6">
                <h3 class="text-sm font-semibold">{{ __('booking.bookings.schedule') }}</h3>
                <div class="mt-3 grid gap-3 rounded-xl bg-muted p-4 text-sm sm:grid-cols-3">
                    <div>
                        <p class="text-xs text-muted-foreground">{{ __('booking.bookings.date') }}</p>
                        <p class="mt-1 font-semibold">
                            {{ $booking->screening?->starts_at?->timezone($timezone)->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ __('booking.bookings.room') }}</p>
                        <p class="mt-1 font-semibold">{{ $booking->screening?->room?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">{{ __('booking.bookings.booking_id') }}</p>
                        <p class="mt-1 font-semibold">#{{ $booking->id }}</p>
                    </div>
                </div>
            </div>
            <div class="border-t border-border pt-6">
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
                <div class="border-t border-border pt-6">
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
            @if (in_array(
                    $booking->status,
                    [\App\Enums\Movie\Booking\BookingStatus::Confirmed, \App\Enums\Movie\Booking\BookingStatus::Completed],
                    true) && $booking->items->isNotEmpty())
                <div class="border-t border-border pt-6">
                    <h3 class="text-sm font-semibold">{{ __('cinema.tickets.title') }}</h3>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($booking->items as $item)
                            <a href="{{ route('user.tickets.show', $item) }}"
                                class="rounded-lg bg-muted p-3 text-sm hover:bg-accent"><span class="font-semibold">
                                    {{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}
                                </span>
                                <span class="ml-2 text-muted-foreground">
                                    {{ $item->ticket_code }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="flex flex-col gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end">
                @if (in_array(
                        $booking->status,
                        [\App\Enums\Movie\Booking\BookingStatus::Held, \App\Enums\Movie\Booking\BookingStatus::PendingPayment],
                        true))
                    <x-admin.button :href="route('user.bookings.checkout', $booking)" icon="arrow-right">
                        {{ __('booking.bookings.pay') }}
                    </x-admin.button>
                @endif
                @if (in_array(
                        $booking->status,
                        [\App\Enums\Movie\Booking\BookingStatus::Held, \App\Enums\Movie\Booking\BookingStatus::PendingPayment],
                        true) && auth()->user()->can('cancel', $booking))
                    <button type="button" data-modal-open="cancel-booking-modal"
                        data-modal-action="{{ route('user.bookings.cancel', $booking) }}" data-modal-method="PATCH"
                        class="ui-action inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-destructive px-3.5 py-2 text-sm font-semibold text-destructive-foreground shadow-sm transition hover:brightness-95">{{ __('booking.bookings.cancel') }}
                    </button>
                @endif
            </div>
        </section>
        <div id="cancel-booking-modal" data-modal hidden
            class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-xl" role="dialog"
                aria-modal="true" aria-labelledby="cancel-booking-title">
                <h2 id="cancel-booking-title" data-modal-title class="text-lg font-semibold">
                    {{ __('booking.bookings.cancel') }}
                </h2>

                <p data-modal-description class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ __('booking.admin.cancel_confirm') }}
                </p>

                <label class="mt-5 block text-sm font-semibold" for="cancel-reason">
                    {{ __('booking.bookings.reason') }}
                </label>

                <textarea id="cancel-reason" data-modal-input name="reason" rows="3" maxlength="500"
                    class="mt-2 w-full rounded-xl border border-border bg-background p-3 text-sm"
                    placeholder="{{ __('booking.bookings.reason') }}"></textarea>

                <div class="mt-6 flex justify-end gap-2"><button type="button" data-modal-close
                        class="rounded-xl border border-border px-3.5 py-2 text-sm font-semibold">{{ __('ui.actions.cancel') }}</button><button
                        type="button" data-modal-confirm
                        class="rounded-xl bg-destructive px-3.5 py-2 text-sm font-semibold text-destructive-foreground">
                        <span data-modal-label>
                            {{ __('ui.actions.confirm') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
