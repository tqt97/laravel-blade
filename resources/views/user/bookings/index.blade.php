<x-layouts.user :title="__('booking.bookings.title')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.bookings.title') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.dashboard.history_description') }}</p>
            </div><x-admin.button :href="route('cinema.movies.index')" icon="arrow-right">{{ __('cinema.public.movies') }}</x-admin.button>
        </div>
        @if ($bookings->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                {{ __('booking.bookings.empty') }}</div>
        @else
            <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border text-left text-sm">
                        <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-5 py-4">{{ __('booking.bookings.resource') }}</th>
                                <th class="px-5 py-4">{{ __('booking.bookings.period') }}</th>
                                <th class="px-5 py-4">{{ __('booking.bookings.status') }}</th>
                                <th class="whitespace-nowrap px-5 py-4 text-right">{{ __('booking.bookings.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">@foreach ($bookings as $booking)
                            <tr class="align-top">
                                <td class="px-5 py-4 font-semibold">{{ $booking->screening?->movie?->title ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                    {{ $booking->screening?->starts_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}<br>{{ $booking->screening?->ends_at?->timezone($booking->screening?->room?->timezone ?? config('app.timezone'))->format('H:i') ?? '—' }}
                                </td>
                                <td class="px-5 py-4"><span
                                        class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ __('booking.status.' . $booking->status->value) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <x-admin.button :href="route('user.bookings.show', $booking)" variant="ghost"
                                        icon="eye" icon-only :title="__('booking.bookings.details')"
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
