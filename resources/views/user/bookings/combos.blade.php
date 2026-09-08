<x-layouts.storefront :title="__('booking.combos.title')">
    <div class="mx-auto max-w-3xl space-y-8 px-5 py-12 sm:px-8">
        <div>
            <a href="{{ route('user.bookings.checkout', $booking) }}" class="text-sm font-semibold text-primary hover:underline">← {{ __('booking.checkout.title') }}</a>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ __('booking.combos.title') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.combos.description') }}</p>
        </div>

        <x-auth.feedback />

        <form method="POST" action="{{ route('user.bookings.combos.store', $booking) }}" class="space-y-4 rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
            @csrf
            @forelse ($concessions as $concession)
                <label class="flex items-center justify-between gap-4 rounded-xl border border-border p-4">
                    <span>
                        <span class="block font-semibold">{{ $concession->name }}</span>
                        <span class="mt-1 block text-sm text-muted-foreground">{{ number_format((int) $concession->price_minor_units, 0, ',', '.') }} {{ $concession->currency }} · {{ $concession->stock === null ? __('booking.combos.unlimited') : __('booking.combos.stock', ['count' => $concession->stock]) }}</span>
                    </span>
                    <input type="number" min="0" max="20" name="quantities[{{ $concession->id }}]" value="{{ old('quantities.'.$concession->id, $booking->concessions->firstWhere('concession_id', $concession->id)?->quantity ?? 0) }}" class="w-24 rounded-lg border border-border bg-background px-3 py-2 text-center">
                </label>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('booking.combos.empty') }}</p>
            @endforelse

            <div class="flex justify-end border-t border-border pt-6">
                <x-admin.button type="submit" icon="save">{{ __('booking.combos.save') }}</x-admin.button>
            </div>
        </form>
    </div>
</x-layouts.storefront>
