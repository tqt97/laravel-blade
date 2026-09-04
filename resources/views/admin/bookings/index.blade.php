<x-layouts.auth :title="__('booking.nav.bookings')" :heading="__('booking.nav.bookings')">
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.admin.bookings_title') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.admin.bookings_description') }}</p>
        </div>@if (session('status'))
            <div role="status"
                class="rounded-xl border border-success/30 bg-success-soft p-4 text-sm text-success-foreground">
        {{ __(session('status')) }}</div>@endif
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-4">{{ __('booking.bookings.resource') }}</th>
                            <th class="px-5 py-4">{{ __('booking.bookings.user') }}</th>
                            <th class="px-5 py-4">{{ __('booking.bookings.period') }}</th>
                            <th class="px-5 py-4">{{ __('booking.bookings.status') }}</th>
                            <th class="px-5 py-4 text-right">{{ __('booking.bookings.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">@forelse ($bookings as $booking)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ $booking->resource?->name ?? '—' }}</td>
                            <td class="px-5 py-4">{{ $booking->user?->name ?? '—' }}<span
                                    class="block text-xs text-muted-foreground">{{ $booking->user?->email }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
                                {{ $booking->start_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} –
                                {{ $booking->end_at->timezone(config('app.timezone'))->format('H:i') }}</td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ __('booking.status.' . $booking->status->value) }}</span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if (in_array($booking->status->value, ['held', 'pending_payment', 'confirmed'], true))
                                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}">@csrf
                                        @method('PATCH')<x-admin.button type="submit" variant="danger" compact
                                            icon="trash">{{ __('booking.bookings.cancel') }}</x-admin.button></form>
                                @else<span class="text-xs text-muted-foreground">—</span>@endif
                            </td>
                    </tr>@empty<tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-muted-foreground">
                                {{ __('booking.bookings.empty') }}</td>
                        </tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $bookings->links() }}</div>
    </div>
</x-layouts.auth>
