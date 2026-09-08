<x-layouts.storefront :title="__('booking.checkout.title')">
    <div class="mx-auto max-w-2xl space-y-8 px-5 py-12 sm:px-8">
        <div>
            <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('cinema.public.movies') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.checkout.title') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.checkout.description') }}</p>
        </div>

        <x-auth.feedback />

        <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div>
                <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ $booking->screening?->room?->name ?? '—' }} ·
                    {{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                </p>
            </div>

            <div class="border-t border-border pt-6">
                <h3 class="text-sm font-semibold">{{ __('booking.checkout.seats') }}</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($booking->items as $item)
                        <span class="rounded-lg bg-muted px-3 py-2 text-sm font-semibold">
                            {{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col justify-between gap-3 border-t border-border pt-6 sm:flex-row sm:items-center">
                <div>
                    <h3 class="text-sm font-semibold">{{ __('booking.checkout.combos') }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ __('booking.checkout.combos_description') }}</p>
                </div>
                @if ($booking->getRawOriginal('status') === 'held')
                    <x-admin.button :href="route('user.bookings.combos', $booking)" variant="secondary" icon="plus">
                        {{ __('booking.checkout.add_combos') }}
                    </x-admin.button>
                @else
                    <span class="text-xs text-muted-foreground">{{ __('booking.checkout.combos_locked') }}</span>
                @endif
            </div>

            <div class="flex items-center justify-between border-t border-border pt-6">
                <span class="text-sm text-muted-foreground">{{ __('booking.checkout.total') }}</span>
                <span class="text-xl font-bold text-primary">
                    {{ number_format((int) $booking->total_minor_units, 0, ',', '.') }} {{ $booking->pricing_currency ?? $booking->currency }}
                </span>
            </div>

            @if ($booking->expires_at)
                <p class="rounded-xl bg-warning-soft p-4 text-sm text-warning-foreground">
                    {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.hold_minutes')]) }}
                </p>
            @endif

            <form method="POST" action="{{ route('user.bookings.pay', $booking) }}">
                @csrf
                <x-admin.button type="submit" icon="save" class="w-full justify-center">
                    {{ __('booking.checkout.pay') }}
                </x-admin.button>
            </form>
        </section>
    </div>
</x-layouts.storefront>
