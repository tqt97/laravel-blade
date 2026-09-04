<x-layouts.user :title="__('booking.nav.dashboard')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div>
            <p class="text-sm font-semibold text-primary">{{ __('booking.nav.group') }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                {{ __('booking.dashboard.welcome', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('booking.dashboard.description') }}
            </p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <a href="{{ route('user.resources.index') }}"
                class="rounded-2xl border border-border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50">
                <p class="text-sm font-semibold">{{ __('booking.dashboard.browse_title') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.dashboard.browse_description') }}</p>
            </a>
            <a href="{{ route('user.bookings.index') }}"
                class="rounded-2xl border border-border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/50">
                <p class="text-sm font-semibold">{{ __('booking.dashboard.history_title') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.dashboard.history_description') }}</p>
            </a>
        </div>
    </div>
</x-layouts.user>
