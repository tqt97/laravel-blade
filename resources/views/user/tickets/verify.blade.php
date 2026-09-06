<x-layouts.user :title="__('cinema.tickets.verify')">
    <div class="mx-auto max-w-lg rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
        <p class="text-sm text-muted-foreground">{{ __('cinema.tickets.verify') }}</p>
        <h1 class="mt-2 text-2xl font-semibold">{{ $ticket->ticket_code }}</h1>
        <p class="mt-4 text-sm">{{ __('booking.status.' . $ticket->status->value) }}</p>
    </div>
</x-layouts.user>
