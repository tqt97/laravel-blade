<x-layouts.movie :title="__('cinema.tickets.verify')">
    <div class="mx-auto max-w-lg px-5 py-12 sm:px-8">
        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-xl">
            <div class="bg-primary-soft p-8 text-center">
                <div class="mx-auto grid size-14 place-items-center rounded-full bg-success text-success-foreground">
                    <span class="text-2xl font-bold" aria-hidden="true">✓</span>
                </div>
                <p class="mt-4 text-sm font-semibold uppercase tracking-[0.18em] text-primary">
                    {{ __('cinema.tickets.verify') }}
                </p>
                <h1 class="mt-2 text-2xl font-semibold">{{ $ticket->ticket_code }}</h1>
            </div>
            <div class="space-y-5 p-6 sm:p-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ __('cinema.tickets.movie') }}
                    </p>
                    <p class="mt-1 text-lg font-semibold">
                        {{ $ticket->booking?->screening?->movie?->title ?? '—' }}
                    </p>
                </div>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">
                            {{ __('cinema.tickets.screening') }}
                        </dt>
                        <dd class="mt-1 font-semibold">
                            {{ $ticket->booking?->screening?->room?->name ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ __('cinema.tickets.seat') }}</dt>
                        <dd class="mt-1 font-semibold">
                            {{ $ticket->screeningSeat?->seat?->row_label }}{{ $ticket->screeningSeat?->seat?->seat_number }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ __('cinema.tickets.starts_at') }}</dt>
                        <dd class="mt-1 font-semibold">
                            {{ $ticket->booking?->screening?->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ __('cinema.tickets.status_label') }}</dt>
                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full border border-success/30 bg-success-soft px-2.5 py-1 text-xs font-semibold text-success-foreground">
                                {{ __('cinema.tickets.status.' . $ticket->status->value) }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>
    </div>
</x-layouts.movie>
