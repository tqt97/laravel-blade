<x-layouts.storefront :title="__('booking.checkout.title')">
    <div class="mx-auto max-w-6xl space-y-8 px-5 py-12 sm:px-8" data-booking-checkout
        data-expires-at="{{ optional($booking->expires_at)->utc()->toIso8601String() }}"
        data-expired-label="{{ __('booking.checkout.expired_notice') }}"
        data-combo-availability-url="{{ route('user.bookings.combo-availability', $booking) }}">
        <div>
            <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('cinema.public.movies') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.checkout.title') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">{{ __('booking.checkout.description') }}</p>
        </div>

        <x-auth.feedback />

        @php
            $currency = strtoupper((string) ($booking->pricing_currency ?? $booking->currency));
            $comboTotal = (int) $booking->concessions->sum('total_minor_units');
            $seatTotal = max(0, (int) $booking->subtotal_minor_units - $comboTotal);
            $isEditable = $booking->getRawOriginal('status') === 'held';
        @endphp

        <form id="booking-payment-form" method="POST" action="{{ route('user.bookings.pay', $booking) }}" data-payment-form data-combo-form data-original-combo-total="{{ $comboTotal }}" data-processing-label="{{ __('booking.checkout.processing') }}">
            @csrf
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <main class="space-y-6">
                <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
                    <div>
                        <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                        <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                        <p class="mt-2 text-sm text-muted-foreground">{{ $booking->screening?->room?->name ?? '—' }} · {{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>

                    <div class="border-t border-border pt-6">
                        <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold">{{ __('booking.checkout.seats') }}</h3><span class="rounded-full bg-primary-soft px-2.5 py-1 text-xs font-semibold text-primary">{{ $booking->items->count() }}</span></div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($booking->items as $item)
                                <div class="flex items-center justify-between rounded-xl bg-muted px-3 py-2.5 text-sm"><span class="font-semibold">{{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}</span><span class="text-xs text-muted-foreground">{{ \App\Support\Money\Money::fromMinorUnits((int) $item->price_minor_units, $currency)->format() }}</span></div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
                    <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ __('booking.checkout.combos') }}</h2><p class="mt-1 text-sm text-muted-foreground">{{ __('booking.checkout.combos_description') }}</p><p data-availability-status class="mt-2 hidden text-xs text-warning-foreground" role="status" aria-live="polite">{{ __('booking.checkout.availability_refresh_failed') }}</p></div>@if (! $isEditable)<span class="shrink-0 text-xs text-muted-foreground">{{ __('booking.checkout.combos_locked') }}</span>@endif</div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @forelse ($concessions as $concession)
                            @php
                                $selectedQuantity = (int) ($booking->concessions->firstWhere('concession_id', $concession->id)?->quantity ?? 0);
                                $maxQuantity = $concession->stock === null ? 20 : min(20, $selectedQuantity + $concession->stock);
                            @endphp
                            @php $soldOut = $concession->stock !== null && $maxQuantity === 0; @endphp
                            <div class="rounded-xl border border-border bg-background p-3 {{ $soldOut ? 'opacity-60 grayscale' : '' }}" data-combo-card data-concession-id="{{ $concession->id }}">
                                <div class="flex gap-3">
                                    @if ($concession->image_url)<img src="{{ $concession->image_url }}" alt="{{ $concession->name }}" width="64" height="64" class="size-16 shrink-0 rounded-lg object-cover" loading="lazy" decoding="async">@else<div class="grid size-16 shrink-0 place-items-center rounded-lg bg-primary-soft text-2xl" aria-hidden="true">🍿</div>@endif
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $concession->name }}</p><p class="mt-1 text-xs text-muted-foreground">{{ \App\Support\Money\Money::fromMinorUnits((int) $concession->price_minor_units, strtoupper((string) $concession->currency))->format() }}</p><p class="mt-1 text-xs text-muted-foreground">{{ $concession->stock === null ? __('booking.combos.unlimited') : __('booking.combos.stock', ['count' => $concession->stock]) }}</p></div>
                                </div>
                                @if ($isEditable)
                                    <div class="mt-3 flex items-center justify-between gap-2" data-combo-control><span class="text-xs text-muted-foreground" data-combo-quantity-status data-selected-label="{{ __('booking.combos.selected_quantity') }}" data-available-label="{{ $maxQuantity }}"></span><span data-combo-sold-out class="hidden rounded-full bg-muted px-2 py-1 text-[11px] font-semibold text-muted-foreground">{{ __('booking.combos.sold_out') }}</span><div class="flex items-center gap-1"><button type="button" data-combo-decrease class="grid size-8 place-items-center rounded-lg border border-border text-sm font-bold transition hover:border-primary" aria-label="{{ __('booking.combos.decrease', ['name' => $concession->name]) }}">−</button><input type="number" min="0" max="{{ $maxQuantity }}" inputmode="numeric" name="quantities[{{ $concession->id }}]" value="{{ $selectedQuantity }}" aria-label="{{ __('booking.combos.quantity_label', ['name' => $concession->name]) }}" data-combo-price="{{ $concession->price_minor_units }}" class="w-12 rounded-lg border border-border bg-card px-2 py-1.5 text-center text-sm font-semibold" @disabled($soldOut)><button type="button" data-combo-increase class="grid size-8 place-items-center rounded-lg border border-border text-sm font-bold transition hover:border-primary" aria-label="{{ __('booking.combos.increase', ['name' => $concession->name]) }}">+</button></div></div>
                                @else
                                    <p class="mt-3 text-xs font-semibold text-primary">{{ __('booking.checkout.selected_quantity', ['selected' => $selectedQuantity, 'available' => $concession->stock === null ? '∞' : $concession->stock]) }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-muted-foreground">{{ __('booking.combos.empty') }}</p>
                        @endforelse
                    </div>
                    @if ($isEditable)
                        <div class="border-t border-border pt-4"><p class="text-sm font-semibold"><span data-combo-count>0</span> {{ __('booking.combos.items_selected') }} · <span data-combo-total>{{ \App\Support\Money\Money::fromMinorUnits($comboTotal, $currency)->format() }}</span></p><p class="mt-1 text-xs text-muted-foreground">{{ __('booking.checkout.combos_pay_hint') }}</p></div>
                    @endif
                </section>
            </main>

            <aside class="sticky bottom-4 z-20 h-fit space-y-4 lg:top-24 lg:bottom-auto">
                <section class="rounded-2xl border border-primary/20 bg-primary-soft/40 p-5 shadow-sm sm:p-6">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('booking.checkout.total') }}</p><h2 class="mt-1 text-lg font-semibold">{{ __('booking.checkout.price_summary') }}</h2></div><span class="rounded-full bg-card px-2.5 py-1 text-xs font-semibold text-primary">{{ $currency }}</span></div>
                    <dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between gap-4 text-muted-foreground"><dt>{{ __('booking.checkout.seats') }}</dt><dd>{{ \App\Support\Money\Money::fromMinorUnits($seatTotal, $currency)->format() }}</dd></div><div class="flex justify-between gap-4 text-muted-foreground"><dt>{{ __('booking.checkout.combos') }}</dt><dd data-checkout-combo-total>{{ \App\Support\Money\Money::fromMinorUnits($comboTotal, $currency)->format() }}</dd></div>@if ((int) $booking->discount_minor_units > 0)<div class="flex justify-between gap-4 text-success"><dt>{{ __('booking.checkout.discount') }}</dt><dd>-{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->discount_minor_units, $currency)->format() }}</dd></div>@endif</dl>
                    <div class="mt-5 flex items-end justify-between gap-4 border-t border-primary/20 pt-5"><span class="text-sm font-semibold">{{ __('booking.checkout.total') }}</span><strong data-checkout-grand-total data-grand-total="{{ (int) $booking->total_minor_units }}" class="text-xl text-primary">{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, $currency)->format() }}</strong></div>
                </section>
                <section class="rounded-2xl border border-border bg-card p-5 shadow-sm"><label for="promo-code" class="text-sm font-semibold">{{ __('booking.checkout.promo_title') }}</label><p class="mt-1 text-xs leading-5 text-muted-foreground">{{ __('booking.checkout.promo_description') }}</p><div class="mt-3 flex gap-2"><input id="promo-code" type="text" maxlength="32" class="min-w-0 flex-1 rounded-xl border border-border bg-background px-3 py-2 text-sm" placeholder="{{ __('booking.checkout.promo_placeholder') }}" aria-describedby="promo-hint"><button type="button" disabled class="rounded-xl border border-border px-3 py-2 text-sm font-semibold text-muted-foreground">{{ __('booking.checkout.promo_apply') }}</button></div><p id="promo-hint" class="mt-2 text-xs text-muted-foreground">{{ __('booking.checkout.promo_coming_soon') }}</p></section>
                @if ($booking->expires_at)<div class="rounded-2xl border border-warning/30 bg-warning-soft p-4" role="status" aria-live="polite"><div class="flex items-center justify-between gap-4 text-sm text-warning-foreground"><span>{{ __('booking.checkout.time_remaining') }}</span><strong data-booking-countdown class="tabular-nums">--:--</strong></div><p class="mt-2 text-xs text-warning-foreground">{{ __('booking.checkout.secure_payment') }}</p></div>@endif
                <div class="hidden rounded-2xl border border-border bg-card p-5 shadow-sm lg:block"><x-admin.button type="submit" icon="save" class="w-full justify-center">{{ __('booking.checkout.pay') }}</x-admin.button></div>
            </aside>
        </div>
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 p-3 shadow-2xl backdrop-blur lg:hidden" role="region" aria-label="{{ __('booking.checkout.price_summary') }}">
            <div class="mx-auto flex max-w-6xl items-center gap-3">
                <div class="min-w-0 flex-1"><p class="text-xs text-muted-foreground">{{ __('booking.checkout.total') }}</p><p class="truncate text-base font-bold text-primary" data-mobile-checkout-total>{{ \App\Support\Money\Money::fromMinorUnits((int) $booking->total_minor_units, $currency)->format() }}</p></div>
                <x-admin.button type="submit" form="booking-payment-form" icon="save" class="shrink-0">{{ __('booking.checkout.pay') }}</x-admin.button>
            </div>
        </div>
        </form>
    </div>
</x-layouts.storefront>
