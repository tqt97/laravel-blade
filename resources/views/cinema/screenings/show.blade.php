<x-layouts.movie :title="$screening->movie->title" :description="__('cinema.public.choose_seats') . ' · ' . $screening->movie->title">
    <div class="mx-auto max-w-6xl space-y-8 px-5 py-12 sm:px-8">
        <x-cinema.booking-stepper current="seats" />
        <div>
            <a href="{{ route('cinema.movies.show', $screening->movie) }}"
                class="text-sm font-semibold text-primary hover:underline">← {{ $screening->movie->title }}
            </a>
            <h1 class="mt-3 text-3xl font-semibold">{{ __('cinema.public.choose_seats') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ $screening->room->name }} ·
                <time datetime="{{ $screening->starts_at->toIso8601String() }}">{{ $screening->starts_at->timezone(config('app.timezone'))->format('D, d/m/Y · H:i') }}</time>–<time datetime="{{ $screening->ends_at->toIso8601String() }}">{{ $screening->ends_at->timezone(config('app.timezone'))->format('H:i') }}</time>
            </p>
            <div
                class="mt-4 inline-flex rounded-full bg-primary-soft px-4 py-2 text-sm font-semibold text-accent-foreground">
                {{ __('cinema.public.seats_summary', $seatSummary) }}
            </div>
        </div>
        @error('seat_ids')
            <p class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive">{{ $message }}</p>
        @enderror
        @guest
            <p class="rounded-xl border border-primary/20 bg-primary-soft/50 p-4 text-sm text-accent-foreground">
                {{ __('cinema.public_login_on_continue') }}
            </p>
        @endguest
        @if ($activeHold)
            <div
                class="flex flex-col justify-between gap-4 rounded-2xl border border-primary/30 bg-primary-soft p-4 text-sm text-accent-foreground sm:flex-row sm:items-center">
                <p>
                    <span class="font-semibold">
                        {{ __('cinema.public.active_hold', ['count' => $activeHold->items->count()]) }}
                    </span>
                    {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.limits.hold_minutes')]) }}
                </p>
                <x-admin.button :href="route('user.bookings.checkout', $activeHold)" icon="arrow-right" compact>
                    {{ __('cinema.public.pay_now') }}
                </x-admin.button>
            </div>
        @endif
        <form method="POST" action="{{ route('cinema.screenings.hold', [$screening->movie, $screening]) }}"
            class="space-y-6" data-seat-picker data-seat-max="{{ config('booking.limits.max_seats') }}" data-combos-per-seat="{{ config('booking.limits.max_combos_per_ticket') }}"
            data-seat-limit-label="{{ __('cinema.seats.max_selected', ['count' => config('booking.limits.max_seats')]) }}"
            data-combo-seat-limit-label="{{ __('booking.combos.max_per_seat') }}"
            data-seat-confirm-title="{{ __('cinema.public.confirm_booking_title') }}"
            data-seat-confirm-description="{{ __('cinema.public.confirm_booking_description') }}"
            data-seat-confirm-movie-label="{{ __('cinema.public.movie_details') }}"
            data-seat-confirm-movie="{{ $screening->movie->title }}"
            data-seat-confirm-showtime-label="{{ __('cinema.public.confirm_showtime') }}"
            data-seat-confirm-showtime="{{ $screening->starts_at->timezone(config('app.timezone'))->format('D, d/m/Y · H:i') }}"
            data-seat-confirm-room-label="{{ __('cinema.public.confirm_room') }}"
            data-seat-confirm-room="{{ $screening->room->name }}"
            data-seat-confirm-seats="{{ __('booking.checkout.seats') }}"
            data-seat-confirm-combos="{{ __('booking.checkout.combos') }}"
            data-seat-confirm-total="{{ __('booking.checkout.subtotal') }}"
            data-seat-confirm-label="{{ __('cinema.public.confirm_seats') }}"
            data-seat-confirm-cancel="{{ __('ui.actions.cancel') }}"
            data-seat-availability-url="{{ route('cinema.screenings.availability', [$screening->movie, $screening]) }}"
            data-seat-conflict-label="{{ __('cinema.seats.availability_changed', ['seats' => ':seats']) }}">
            @csrf
            <input type="hidden" name="idempotency_key"
                value="{{ old('idempotency_key', $activeHold?->idempotency_key ?? (string) Str::uuid()) }}">
            @php($initialSeatCount = count($activeHoldSeatIds))
            @php($initialSeatTotal = (int) ($activeHold?->items?->sum('price_minor_units') ?? 0))
            @php($initialComboTotal = (int) ($activeHold?->concessions?->sum('total_minor_units') ?? 0))
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <section class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8 lg:col-start-1">
                    <div
                        class="mx-auto mb-8 max-w-md rounded-full bg-slate-900 py-2 text-center text-xs font-semibold uppercase tracking-[.2em] text-white">
                        {{ __('cinema.seats.screen') }}
                    </div>
                    <div class="overflow-x-auto pb-2">
                    <div class="mx-auto grid min-w-[35rem] max-w-2xl gap-3 sm:min-w-0">
                        @foreach($screening->screeningSeats->groupBy(fn($item) => $item->seat->row_label) as $row => $seats)
                        <div class="flex items-center gap-3">
                            <span class="w-6 text-center text-xs font-bold text-muted-foreground">
                                {{ $row }}
                            </span>
                            <div class="grid flex-1 gap-2"
                                style="grid-template-columns:repeat({{ min(12, max(1, $seats->count())) }},minmax(0,1fr));">
                                @foreach($seats as $screeningSeat)

                                @php($isOwnHold = in_array((int) $screeningSeat->seat_id, $activeHoldSeatIds, true))
                                @php($available = $screeningSeat->isAvailableForSelection() || $isOwnHold)

                                <button type="button" data-seat-id="{{ $screeningSeat->seat_id }}"
                                    data-seat-label="{{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }}"
                                    data-seat-price="{{ $screeningSeat->price_minor_units }}"
                                    data-seat-type="{{ $screeningSeat->seat->getRawOriginal('seat_type') }}"
                                    data-seat-own-hold="{{ $isOwnHold ? 'true' : 'false' }}"
                                    data-seat-selected="{{ $isOwnHold ? 'true' : 'false' }}"
                                    aria-pressed="{{ $isOwnHold ? 'true' : 'false' }}" @disabled(!$available)
                                    aria-label="{{ __('cinema.seats.seat_label', ['seat' => $screeningSeat->seat->row_label . $screeningSeat->seat->seat_number, 'type' => $screeningSeat->seat->seat_type->name]) }}"
                                    class="relative aspect-square min-h-10 min-w-10 rounded-lg border text-xs font-bold transition duration-200 {{ $isOwnHold ? 'border-primary bg-primary text-primary-foreground shadow-md ring-2 ring-primary/30 hover:border-primary-strong' : ($available ? ($screeningSeat->seat->getRawOriginal('seat_type') === 'vip' ? 'border-primary/50 bg-primary-soft hover:border-primary' : 'border-border bg-background hover:border-primary hover:bg-primary/10') : 'cursor-not-allowed border-border bg-muted text-muted-foreground line-through') }}"
                                    title="{{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }}">{{ $screeningSeat->seat->seat_number }}
                                    @if ($screeningSeat->seat->getRawOriginal('seat_type') === 'vip' && ($available || $isOwnHold))
                                        <span class="pointer-events-none absolute bottom-1 left-1/2 -translate-x-1/2 rounded bg-amber-200 px-1 text-[8px] font-black leading-3 text-amber-950 dark:bg-amber-300 dark:text-amber-950" aria-hidden="true">VIP</span>
                                    @endif
                                    @if($isOwnHold)
                                        <span
                                            class="pointer-events-none absolute right-1 top-1 text-[10px] leading-none text-primary-foreground"
                                            aria-hidden="true">✓</span>
                                    @elseif($available)
                                        <span data-seat-selected-indicator
                                            class="pointer-events-none absolute right-1 top-1 hidden text-[10px] leading-none text-primary-foreground"
                                            aria-hidden="true">✓</span>
                                    @else
                                        <span class="pointer-events-none absolute right-1 top-1 text-[10px] leading-none"
                                            aria-hidden="true">×</span>
                                    @endif
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                    </div>
                    <div class="mt-8 flex flex-wrap justify-center gap-4 text-xs text-muted-foreground">
                        <span class="inline-flex items-center gap-1.5">
                            <i class="size-3 rounded border border-border bg-background"></i>
                            {{ __('cinema.seats.available') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="grid size-4 place-items-center rounded border border-amber-400 bg-amber-100 text-[8px] text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">◆</i>
                            {{ __('cinema.seats.vip') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="size-3 rounded border border-primary/40 bg-primary-soft"></i>
                            {{ __('cinema.seats.held_by_you') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <i
                                class="grid size-3 place-items-center rounded bg-muted text-[10px] leading-none text-muted-foreground">×</i>
                            {{ __('cinema.seats.unavailable') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="size-3 rounded bg-primary ring-2 ring-primary/20"></i>
                            {{ __('cinema.seats.selected') }}
                        </span>
                    </div>
                    <p data-availability-status
                        class="mt-4 hidden rounded-xl bg-warning-soft p-3 text-xs text-warning-foreground" role="status"
                        aria-live="polite">
                        {{ __('cinema.seats.availability_refresh_failed') }}
                    </p>
                </section>
                <section class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8 lg:col-start-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                                {{ __('booking.combos.title') }}
                            </p>
                            <h2 class="mt-1 text-xl font-semibold">
                                {{ __('booking.checkout.combos') }}
                            </h2>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                                {{ __('booking.checkout.combos_description') }}
                            </p>
                        </div>
                        <span
                            class="hidden rounded-full bg-primary-soft px-3 py-1 text-xs font-semibold text-primary sm:inline-flex">
                            {{ __('booking.combos.optional') }}
                        </span>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @forelse ($concessions as $concession)
                        @php($selectedQuantity = (int) ($activeHold?->concessions?->firstWhere('concession_id', $concession->id)?->quantity ?? 0))
                        @php($maxQuantity = $concession->stock === null ? (int) config('booking.limits.max_combo_quantity') : min((int) config('booking.limits.max_combo_quantity'), $selectedQuantity + (int) $concession->stock))
                        @php($soldOut = $concession->stock !== null && $maxQuantity === 0)
                        <div data-combo-card
                            class="rounded-xl border border-border bg-background p-3 {{ $soldOut ? 'opacity-60 grayscale' : '' }}">
                            <div class="flex gap-3">
                                @if ($concession->image_url)
                                    <img src="{{ $concession->image_url }}" alt="{{ $concession->name }}" width="72"
                                        height="72" class="size-[4.5rem] shrink-0 rounded-lg object-cover" loading="lazy"
                                        decoding="async">
                                @else
                                    <div class="grid size-[4.5rem] shrink-0 place-items-center rounded-lg bg-primary-soft text-2xl"
                                        aria-hidden="true">
                                        🍿
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $concession->name }}</p>
                                    <p class="mt-1 text-sm font-medium text-primary">
                                        {{ \App\Support\Money\Money::fromMinorUnits((int) $concession->price_minor_units, strtoupper((string) $concession->currency))->format() }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ $concession->stock === null ? __('booking.combos.unlimited') : __('booking.combos.stock', ['count' => $concession->stock]) }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3" data-combo-control>
                                <span data-combo-sold-out
                                    class="{{ $soldOut ? '' : 'hidden' }} rounded-full bg-muted px-2 py-1 text-[11px] font-semibold text-muted-foreground">
                                    {{ __('booking.combos.sold_out') }}
                                </span>
                                <span data-combo-quantity-status
                                    data-selected-label="{{ __('booking.combos.selected_quantity') }}"
                                    data-available-label="{{ $maxQuantity }}" class="text-xs text-muted-foreground">
                                </span>
                                <div class="ml-auto flex items-center gap-1">
                                    <button type="button" data-combo-decrease
                                        class="grid size-9 place-items-center rounded-lg border border-border text-lg font-semibold transition hover:border-primary hover:bg-primary/10"
                                        aria-label="{{ __('booking.combos.decrease', ['name' => $concession->name]) }}">−</button>
                                    <input type="number" min="0" max="{{ $maxQuantity }}" inputmode="numeric"
                                        name="quantities[{{ $concession->id }}]"
                                        value="{{ old('quantities.' . $concession->id, $selectedQuantity) }}"
                                        aria-label="{{ __('booking.combos.quantity_label', ['name' => $concession->name]) }}"
                                        data-combo-name="{{ $concession->name }}"
                                        data-combo-price="{{ $concession->price_minor_units }}"
                                        class="w-12 rounded-lg border border-border bg-card px-2 py-2 text-center text-sm font-semibold"
                                        @disabled($soldOut)>
                                    <button type="button" data-combo-increase
                                        class="grid size-9 place-items-center rounded-lg border border-border text-lg font-semibold transition hover:border-primary hover:bg-primary/10"
                                        aria-label="{{ __('booking.combos.increase', ['name' => $concession->name]) }}">+</button>
                                </div>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-muted-foreground sm:col-span-2">
                            {{ __('booking.combos.empty') }}
                        </p>
                        @endforelse
                    </div>
                    @error('quantities')
                        <p class="mt-4 rounded-xl bg-destructive/10 p-3 text-sm text-destructive">{{ $message }}</p>
                    @enderror
                    <p class="mt-5 border-t border-border pt-4 text-sm text-muted-foreground">
                        <span data-combo-count>0</span>
                        {{ __('booking.combos.items_selected') }} ·
                        <strong data-combo-total class="text-foreground">
                            0 {{ $screening->currency }}
                        </strong>
                    </p>
                </section>
                <aside data-seat-summary data-currency="{{ $screening->currency }}"
                    class="sticky bottom-4 z-10 h-fit rounded-2xl border border-primary/20 bg-primary-soft/40 p-5 shadow-sm lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:top-24">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                                {{ __('cinema.seats.summary') }}
                            </p>
                            <h2 class="mt-1 text-lg font-semibold">
                                {{ __('cinema.seats.selected_summary') }}
                            </h2>
                        </div>
                        <span
                            class="grid size-9 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
                            data-seat-summary-count>
                            {{ $initialSeatCount }}
                        </span>
                    </div>
                    <div data-seat-summary-empty
                        class="{{ $initialSeatCount > 0 ? 'hidden' : '' }} mt-6 rounded-xl border border-dashed border-primary/30 bg-card/70 p-4 text-center text-sm text-muted-foreground">
                        {{ __('cinema.seats.summary_empty') }}
                    </div>
                    <div data-seat-summary-seats
                        class="{{ $initialSeatCount > 0 ? '' : 'hidden' }} mt-4 max-h-52 space-y-2 overflow-y-auto">
                        @foreach ($activeHold?->items ?? [] as $item)
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-card px-3 py-2 text-sm">
                                <span class="font-semibold">
                                    {{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}
                                </span>
                                <span class="text-xs font-bold text-foreground">
                                    {{ \App\Support\Money\Money::fromMinorUnits((int) $item->price_minor_units, strtoupper((string) $screening->currency))->format() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" data-seat-suggest
                        class="mt-4 w-full rounded-xl border border-primary/30 px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary/10">
                        {{ __('cinema.seats.suggest') }}
                    </button>
                    <div class="mt-5 space-y-2 border-t border-primary/20 pt-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">
                                {{ __('booking.checkout.seats') }}
                            </span>
                            <span data-seat-summary-seat-total>
                                {{ \App\Support\Money\Money::fromMinorUnits($initialSeatTotal, strtoupper((string) $screening->currency))->format() }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">
                                {{ __('booking.checkout.combos') }}
                            </span><span data-seat-summary-combo-total>
                                {{ \App\Support\Money\Money::fromMinorUnits($initialComboTotal, strtoupper((string) $screening->currency))->format() }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-end justify-between border-t border-primary/20 pt-4">
                        <span class="text-sm text-muted-foreground">
                            {{ __('booking.checkout.subtotal') }}
                        </span>
                        <strong data-seat-summary-total
                            class="text-xl text-primary">{{ \App\Support\Money\Money::fromMinorUnits($initialSeatTotal + $initialComboTotal, strtoupper((string) $screening->currency))->format() }}</strong>
                    </div>
                    <p data-seat-limit-status
                        class="mt-4 hidden rounded-xl bg-warning-soft p-3 text-xs text-warning-foreground" role="status"
                        aria-live="polite"></p>
                    <div class="mt-5 border-t border-primary/20 pt-4">
                        <p class="text-sm text-muted-foreground">
                            <span data-seat-count>{{ $initialSeatCount }}</span>
                            {{ __('cinema.seats.selected_count') }}
                        </p>
                        <x-admin.button type="submit" icon="arrow-right" data-seat-submit
                            :disabled="$initialSeatCount === 0" class="mt-3 w-full justify-center">
                            {{ __('cinema.public.continue') }}
                        </x-admin.button>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</x-layouts.movie>
