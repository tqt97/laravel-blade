<x-layouts.user :title="__('booking.bookings.details')">
    <div class="mx-auto max-w-3xl space-y-8">
        <div><a href="{{ route('user.bookings.index') }}" class="text-sm font-semibold text-primary hover:underline">←
                {{ __('booking.bookings.title') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.bookings.details') }}</h1>
        </div>
        @if (session('status'))
            <div role="status"
                class="rounded-xl border border-success/30 bg-success-soft p-4 text-sm text-success-foreground">
        {{ __(session('status')) }}</div>@endif
        @if ($errors->any())
            <div role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive-soft p-4 text-sm text-destructive-foreground">
        {{ $errors->first() }}</div>@endif
        <section class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm text-muted-foreground">{{ __('booking.bookings.resource') }}</p>
                    <h2 class="mt-1 text-xl font-semibold">{{ $booking->resource?->name ?? '—' }}</h2>
                </div><span
                    class="w-fit rounded-full bg-muted px-3 py-1 text-xs font-semibold">{{ __('booking.status.' . $booking->status->value) }}</span>
            </div>
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.start') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->start_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('booking.bookings.end') }}</dt>
                    <dd class="mt-1 text-sm">
                        {{ $booking->end_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
                </div>
            </dl>@if ($booking->status->value === 'held')
                <p class="rounded-xl bg-warning-soft p-4 text-sm text-warning-foreground">
            {{ __('booking.bookings.hold_hint', ['minutes' => config('booking.hold_minutes')]) }}</p>@endif
            <div class="flex flex-col gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end">
                @if ($booking->status->value === 'held')
                    <form method="POST" action="{{ route('user.bookings.confirm', $booking) }}">@csrf<x-admin.button
                type="submit" icon="save">{{ __('booking.bookings.confirm') }}</x-admin.button></form>@endif
                @if (in_array($booking->status->value, ['held', 'pending_payment', 'confirmed'], true))
                    <form method="POST" action="{{ route('user.bookings.cancel', $booking) }}">@csrf
                        @method('PATCH')<x-admin.button type="submit" variant="danger"
                icon="trash">{{ __('booking.bookings.cancel') }}</x-admin.button></form>@endif
            </div>
        </section>
    </div>
</x-layouts.user>
