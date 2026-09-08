<x-layouts.user :title="__('booking.bookings.details')">
    <div class="mx-auto max-w-3xl space-y-8">
        <div><a href="{{ route('user.bookings.index') }}" class="text-sm font-semibold text-primary hover:underline">←
                {{ __('booking.bookings.title') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.bookings.details') }}</h1>
        </div>
        <x-auth.feedback />
        <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm text-muted-foreground">{{ __('cinema.public.movie_details') }}</p>
                    <h2 class="mt-1 text-xl font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</h2>
                </div><span
                    class="w-fit rounded-full bg-muted px-3 py-1 text-xs font-semibold">{{ __('booking.status.' . $booking->status->value) }}</span>
            </div>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.start') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.end') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->screening?->ends_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            </dl>@if ($booking->status->value === 'held')
                <p class="rounded-xl bg-warning-soft p-4 text-sm text-warning-foreground">
            {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.hold_minutes')]) }}</p>@endif
            @if (in_array($booking->status->value, ['confirmed', 'completed'], true) && $booking->items->isNotEmpty())
                <div class="border-t border-border pt-6"><h3 class="text-sm font-semibold">{{ __('cinema.tickets.title') }}</h3><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach ($booking->items as $item)<a href="{{ route('user.tickets.show', $item) }}" class="rounded-lg bg-muted p-3 text-sm hover:bg-accent"><span class="font-semibold">{{ $item->screeningSeat?->seat?->row_label }}{{ $item->screeningSeat?->seat?->seat_number }}</span><span class="ml-2 text-muted-foreground">{{ $item->ticket_code }}</span></a>@endforeach</div></div>
            @endif
            <div class="flex flex-col gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end">
                @if (in_array($booking->status->value, ['held', 'pending_payment'], true))
                    <x-admin.button :href="route('user.bookings.checkout', $booking)" icon="arrow-right">
                        {{ __('booking.bookings.pay') }}
                    </x-admin.button>
                @endif
                @if (in_array($booking->status->value, ['held', 'pending_payment'], true))
                    <form method="POST" action="{{ route('user.bookings.cancel', $booking) }}">@csrf
                        @method('PATCH')<x-admin.button type="submit" variant="danger"
                icon="trash">{{ __('booking.bookings.cancel') }}</x-admin.button></form>@endif
            </div>
        </section>
    </div>
</x-layouts.user>
