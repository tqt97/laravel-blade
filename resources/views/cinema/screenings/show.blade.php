<x-layouts.storefront :title="$screening->movie->title">
    <div class="mx-auto max-w-5xl space-y-8 px-5 py-12 sm:px-8">
        <div>
            <a href="{{ route('cinema.movies.show', $screening->movie) }}"
                class="text-sm font-semibold text-primary hover:underline">← {{ $screening->movie->title }}</a>
            <h1 class="mt-3 text-3xl font-semibold">{{ __('cinema.public.choose_seats') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ $screening->room->name }} ·
                {{ $screening->starts_at->timezone($screening->room->timezone)->format('D, d/m/Y · H:i') }}–{{ $screening->ends_at->timezone($screening->room->timezone)->format('H:i') }}
            </p>
            <div
                class="mt-4 inline-flex rounded-full bg-primary-soft px-4 py-2 text-sm font-semibold text-accent-foreground">
                {{ __('cinema.public.seats_summary', $seatSummary) }}</div>
        </div>
        {{-- <x-auth.feedback /> --}}
        @error('seat_ids')
        <p class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive">{{ $message }}</p>@enderror
        @guest<p class="rounded-xl border border-primary/20 bg-primary-soft/50 p-4 text-sm text-accent-foreground">
        {{ __('cinema.public_login_on_continue') }}</p>@endguest
        @if ($activeHold)
            <div class="flex flex-col justify-between gap-4 rounded-2xl border border-primary/30 bg-primary-soft p-4 text-sm text-accent-foreground sm:flex-row sm:items-center">
                <p><span class="font-semibold">{{ __('cinema.public.active_hold', ['count' => $activeHold->items->count()]) }}</span> {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.hold_minutes')]) }}</p>
                <x-admin.button :href="route('user.bookings.checkout', $activeHold)" icon="arrow-right" compact>{{ __('cinema.public.pay_now') }}</x-admin.button>
            </div>
        @endif
        <form method="POST" action="{{ route('cinema.screenings.hold', $screening) }}" class="space-y-6"
            data-seat-picker data-seat-confirm-title="{{ __('cinema.public.confirm_seats_title') }}"
            data-seat-confirm-description="{{ __('cinema.public.confirm_seats_description') }}"
            data-seat-confirm-label="{{ __('cinema.public.confirm_seats') }}"
            data-seat-confirm-cancel="{{ __('ui.actions.cancel') }}">
            @csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
            <section class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8">
                <div
                    class="mx-auto mb-8 max-w-md rounded-full bg-slate-900 py-2 text-center text-xs font-semibold uppercase tracking-[.2em] text-white">
                    {{ __('cinema.seats.screen') }}</div>
                <div class="mx-auto grid max-w-2xl gap-3">
                    @foreach($screening->screeningSeats->groupBy(fn($item) => $item->seat->row_label) as $row => $seats)
                    <div class="flex items-center gap-3"><span
                            class="w-6 text-center text-xs font-bold text-muted-foreground">{{ $row }}</span>
                        <div class="grid flex-1 gap-2"
                            style="grid-template-columns:repeat({{ min(12, max(1, $seats->count())) }},minmax(0,1fr));">
                            @foreach($seats as $screeningSeat)@php($available = $screeningSeat->isAvailableForSelection()) @php($isOwnHold = in_array((int) $screeningSeat->seat_id, $activeHoldSeatIds, true))<button
                                type="button" data-seat-id="{{ $screeningSeat->seat_id }}" data-seat-label="{{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }}" data-seat-price="{{ $screeningSeat->price_minor_units }}" aria-pressed="false" @disabled(!$available)
                                class="relative aspect-square rounded-lg border text-xs font-bold transition duration-200 {{ $isOwnHold ? 'cursor-not-allowed border-primary/40 bg-primary-soft text-primary' : ($available ? 'border-border bg-background hover:border-primary hover:bg-primary/10' : 'cursor-not-allowed border-border bg-muted text-muted-foreground line-through') }}"
                                title="{{ $screeningSeat->seat->row_label }}{{ $screeningSeat->seat->seat_number }}">{{ $screeningSeat->seat->seat_number }}@if($isOwnHold)<span class="pointer-events-none absolute right-1 top-1 text-[10px] leading-none text-primary" aria-hidden="true">✓</span>@elseif($available)<span data-seat-selected-indicator class="pointer-events-none absolute right-1 top-1 hidden text-[10px] leading-none text-primary-foreground" aria-hidden="true">✓</span>@else<span class="pointer-events-none absolute right-1 top-1 text-[10px] leading-none" aria-hidden="true">×</span>@endif</button>@endforeach
                        </div>
                    </div>@endforeach
                </div>
                <div class="mt-8 flex flex-wrap justify-center gap-4 text-xs text-muted-foreground"><span class="inline-flex items-center gap-1.5"><i class="size-3 rounded border border-border bg-background"></i>{{ __('cinema.seats.available') }}</span><span class="inline-flex items-center gap-1.5"><i class="size-3 rounded border border-primary/40 bg-primary-soft"></i>{{ __('cinema.seats.held_by_you') }}</span><span class="inline-flex items-center gap-1.5"><i class="grid size-3 place-items-center rounded bg-muted text-[10px] leading-none text-muted-foreground">×</i>{{ __('cinema.seats.unavailable') }}</span><span class="inline-flex items-center gap-1.5"><i class="size-3 rounded bg-primary ring-2 ring-primary/20"></i>{{ __('cinema.seats.selected') }}</span></div>
            </section>
            <aside data-seat-summary data-currency="{{ $screening->currency }}" class="h-fit rounded-2xl border border-primary/20 bg-primary-soft/40 p-5 shadow-sm lg:sticky lg:top-24">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary">{{ __('cinema.seats.summary') }}</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ __('cinema.seats.selected_summary') }}</h2>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-bold text-primary-foreground" data-seat-summary-count>0</span>
                </div>
                <div data-seat-summary-empty class="mt-6 rounded-xl border border-dashed border-primary/30 bg-card/70 p-4 text-center text-sm text-muted-foreground">{{ __('cinema.seats.summary_empty') }}</div>
                <div data-seat-summary-seats class="mt-4 hidden max-h-52 space-y-2 overflow-y-auto"></div>
                <div class="mt-5 flex items-end justify-between border-t border-primary/20 pt-4">
                    <span class="text-sm text-muted-foreground">{{ __('cinema.seats.total') }}</span>
                    <strong data-seat-summary-total class="text-xl text-primary">0 {{ $screening->currency }}</strong>
                </div>
            </aside>
            </div>
            <section
                class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:flex-row">
                <p class="text-sm text-muted-foreground"><span data-seat-count>0</span>
                    {{ __('cinema.seats.selected_count') }}</p><x-admin.button type="submit" icon="arrow-right"
                    data-seat-submit disabled>{{ __('cinema.public.continue') }}</x-admin.button>
            </section>
        </form>
    </div>
</x-layouts.storefront>
