<x-layouts.movie :title="__($canRebook ? 'booking.checkout.expired_title' : 'booking.checkout.screening_expired_title')">
    <div class="mx-auto flex min-h-[60vh] max-w-2xl items-center px-5 py-12 sm:px-8">
        <section class="w-full rounded-2xl border border-warning/30 bg-card p-6 text-center shadow-sm sm:p-10">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-warning-soft text-warning-foreground"
                aria-hidden="true">
                <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </svg>
            </div>
            <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-warning-foreground">
                {{ __($canRebook ? 'booking.checkout.expired_status' : 'booking.checkout.screening_expired_status') }}
            </p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-card-foreground sm:text-3xl">
                {{ __($canRebook ? 'booking.checkout.expired_title' : 'booking.checkout.screening_expired_title') }}
            </h1>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-muted-foreground">
                {{ __($canRebook ? 'booking.checkout.expired_description' : 'booking.checkout.screening_expired_description') }}
            </p>

            <div class="mx-auto mt-6 max-w-md rounded-xl border border-border bg-muted/60 p-4 text-left text-sm">
                <p class="font-semibold text-foreground">{{ $booking->screening?->movie?->title ?? '—' }}</p>
                <p class="mt-2 text-muted-foreground">
                    {{ $booking->screening?->room?->name ?? '—' }} ·
                    {{ $booking->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                </p>
            </div>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-admin.button :href="$canRebook
                    ? route('cinema.screenings.show', [$booking->screening->movie, $booking->screening])
                    : route('cinema.movies.index')" icon="arrow-right" class="justify-center">
                    {{ __($canRebook ? 'booking.checkout.expired_action' : 'booking.checkout.screening_expired_action') }}
                </x-admin.button>
                <x-admin.button :href="route('cinema.movies.index')" variant="secondary" icon="arrow-left" class="justify-center">
                    {{ __('cinema.public.movies') }}
                </x-admin.button>
            </div>
        </section>
    </div>
</x-layouts.movie>
