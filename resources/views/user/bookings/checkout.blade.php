<x-layouts.movie :title="__('booking.checkout.title')">
    @php
        $expiresAt = \App\Support\Time\BookingClock::parseStored($booking->getRawOriginal('expires_at'));
    @endphp

    <div class="mx-auto max-w-6xl space-y-8 px-5 py-12 pb-32 sm:px-8 sm:pb-12" data-booking-checkout
        data-expires-at="{{ $expiresAt?->toIso8601String() }}"
        data-expired-label="{{ __('booking.checkout.expired_notice') }}"
        data-combo-availability-url="{{ route('user.bookings.combo-availability', $booking) }}">
        <x-cinema.booking-stepper current="review" />
        <div>
            @if ($booking->status === \App\Enums\Movie\Booking\BookingStatus::Held)
                <a href="{{ route('cinema.screenings.show', [$booking->screening->movie, $booking->screening]) }}"
                    class="text-sm font-semibold text-primary hover:underline">←
                    {{ __('booking.checkout.edit_selection') }}</a>
            @else
                <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-primary hover:underline">←
                    {{ __('cinema.public.movies') }}</a>
            @endif
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.checkout.title') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">{{ __('booking.checkout.description') }}</p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs text-muted-foreground">
                <span class="rounded-full bg-success-soft px-3 py-1.5 text-success-foreground">🔒 {{ __('booking.checkout.secure_payment') }}</span>
                <span class="rounded-full bg-primary-soft px-3 py-1.5 text-accent-foreground">⏱ {{ __('booking.checkout.hold_guarantee') }}</span>
            </div>
        </div>

        <x-auth.feedback />

        @php
            $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
            $comboTotal = (int) $booking->concessions->sum('total_minor_units');
            $seatTotal = max(0, (int) $booking->subtotal_minor_units - $comboTotal);
            $isEditable = $booking->status === \App\Enums\Movie\Booking\BookingStatus::Held;
        @endphp

        <form id="booking-payment-form" method="POST" action="{{ route('user.bookings.pay', $booking) }}"
            data-payment-form data-processing-label="{{ __('booking.checkout.processing') }}">
            @csrf
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <main class="space-y-6">
                    <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
                        <div>
                            <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                            <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ $booking->screening?->room?->name ?? '—' }}
                                ·
                                {{ $booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                            </p>
                        </div>

                        <div class="border-t border-border pt-6">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold">{{ __('booking.checkout.seats') }}</h3><span
                                    class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-semibold text-primary">{{ $booking->items->count() }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($booking->items as $item)
                                    <div
                                        class="flex items-center justify-between rounded-xl bg-muted px-3 py-2.5 text-sm">
                                        <span
                                            class="font-semibold">{{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}</span><span
                                            class="text-xs text-muted-foreground">{{ \App\Support\Money\Money::fromMinorUnits((int) $item->price_minor_units, $currency)->format() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
                        <div>
                            <h2 class="text-lg font-semibold">{{ __('booking.checkout.combos') }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ __('booking.checkout.combos_locked') }}
                            </p>
                        </div>
                        @if ($booking->concessions->isNotEmpty())
                            <div class="divide-y divide-border rounded-xl border border-border bg-background">
                                @foreach ($booking->concessions as $line)
                                    <div class="flex items-center justify-between gap-4 p-4 text-sm">
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ $line->concession?->name ?? '—' }}</p>
                                            <p class="mt-1 text-xs text-muted-foreground">{{ $line->quantity }} ×
                                                {{ \App\Support\Money\Money::fromMinorUnits((int) $line->unit_price_minor_units, $currency)->format() }}
                                            </p>
                                        </div><span
                                            class="shrink-0 font-semibold">{{ \App\Support\Money\Money::fromMinorUnits((int) $line->total_minor_units, $currency)->format() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground">
                                {{ __('booking.success.no_combos') }}</p>
                        @endif
                    </section>
                </main>

                <aside class="sticky bottom-4 z-20 h-fit space-y-4 lg:top-24 lg:bottom-auto">
                    <section class="rounded-2xl border border-primary/20 bg-primary-soft/40 p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                                    {{ __('booking.checkout.total') }}</p>
                                <h2 class="mt-1 text-lg font-semibold">{{ __('booking.checkout.price_summary') }}</h2>
                            </div><span
                                class="rounded-full bg-card px-2.5 py-1 text-xs font-semibold text-primary">{{ $currency }}</span>
                        </div>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4 text-muted-foreground">
                                <dt>{{ __('booking.checkout.seats') }}</dt>
                                <dd>{{ \App\Support\Money\Money::fromMinorUnits($seatTotal, $currency)->format() }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4 text-muted-foreground">
                                <dt>{{ __('booking.checkout.combos') }}</dt>
                                <dd data-checkout-combo-total>
                                    {{ \App\Support\Money\Money::fromMinorUnits($comboTotal, $currency)->format() }}
                                </dd>
                            </div>
                            @if ((int) $booking->discount_minor_units > 0)
                                <div class="flex justify-between gap-4 text-success">
                                    <dt>{{ __('booking.checkout.discount') }}</dt>
                                    <dd>-{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->discount_minor_units, $currency)->format() }}
                                    </dd>
                                </div>
                            @endif
                        </dl>
                        <div class="mt-5 flex items-end justify-between gap-4 border-t border-primary/20 pt-5"><span
                                class="text-sm font-semibold">{{ __('booking.checkout.total') }}</span><strong
                                data-checkout-grand-total data-grand-total="{{ (int) $booking->total_minor_units }}"
                                class="text-xl text-primary">{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, $currency)->format() }}</strong>
                        </div>
                    </section>
                    <section class="rounded-2xl border border-border bg-card p-5 shadow-sm"><label for="promo-code"
                            class="text-sm font-semibold">{{ __('booking.checkout.promo_title') }}</label>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ __('booking.checkout.promo_description') }}</p>
                        <div class="mt-3 flex gap-2"><input id="promo-code" name="code" type="text" maxlength="32"
                                value="{{ old('code', $booking->coupon_code) }}"
                                class="min-w-0 flex-1 rounded-xl border border-border bg-background px-3 py-2 text-sm"
                                placeholder="{{ __('booking.checkout.promo_placeholder') }}"
                                aria-describedby="promo-hint"><button type="submit" formmethod="POST"
                                formaction="{{ route('user.bookings.coupon.apply', $booking) }}" formnovalidate
                                class="rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground">{{ __('booking.checkout.promo_apply') }}</button>
                        </div>
                        <p id="promo-hint" class="mt-2 text-xs text-muted-foreground">
                            {{ $booking->coupon_code ? __('booking.checkout.promo_applied', ['code' => $booking->coupon_code]) : __('booking.checkout.promo_hint') }}
                        </p>
                        @error('code')
                            <p class="mt-2 text-xs text-danger" role="alert">{{ $message }}</p>
                        @enderror
                    </section>
                    @if ($booking->expires_at)
                        <div class="rounded-2xl border border-warning/30 bg-warning-soft p-4" role="status"
                            aria-live="polite">
                            <div class="flex items-center justify-between gap-4 text-sm text-warning-foreground">
                                <span>{{ __('booking.checkout.time_remaining') }}</span><strong data-booking-countdown
                                    class="tabular-nums">--:--</strong>
                            </div>
                            <p class="mt-2 text-xs text-warning-foreground">{{ __('booking.checkout.secure_payment') }}
                            </p>
                        </div>
                    @endif
                    <div class="hidden rounded-2xl border border-border bg-card p-5 shadow-sm lg:block"><x-admin.button
                            type="submit" icon="save"
                            class="w-full justify-center">{{ __('booking.checkout.pay') }}</x-admin.button></div>
                </aside>
            </div>
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 p-3 shadow-2xl backdrop-blur lg:hidden"
                role="region" aria-label="{{ __('booking.checkout.price_summary') }}">
                <div class="mx-auto flex max-w-6xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted-foreground">{{ __('booking.checkout.total') }}</p>
                        <p class="truncate text-base font-bold text-primary" data-mobile-checkout-total>
                            {{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, $currency)->format() }}
                        </p>
                    </div>
                    <x-admin.button type="submit" form="booking-payment-form" icon="save"
                        class="shrink-0">{{ __('booking.checkout.pay') }}</x-admin.button>
                </div>
            </div>
        </form>
    </div>
</x-layouts.movie>
