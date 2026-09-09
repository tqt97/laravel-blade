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
                @php($selectedQuantity = (int) ($booking->concessions->firstWhere('concession_id', $concession->id)?->quantity ?? 0))
                @php($maxQuantity = $concession->stock === null ? (int) config('booking.limits.max_combo_quantity') : min((int) config('booking.limits.max_combo_quantity'), $selectedQuantity + $concession->stock))
                <label class="flex items-center gap-4 rounded-xl border border-border bg-background p-4 transition has-[:focus-visible]:border-primary has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-primary/10">
                    @if ($concession->image_url)
                        <img src="{{ $concession->image_url }}" alt="{{ $concession->name }}" class="size-20 shrink-0 rounded-xl object-cover" loading="lazy">
                    @else
                        <div class="grid size-20 shrink-0 place-items-center rounded-xl bg-primary-soft text-3xl" aria-hidden="true">🍿</div>
                    @endif
                    <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $concession->name }}</span><span class="mt-1 block text-sm text-muted-foreground">{{ \App\Support\Money\Money::fromMinorUnits((int) $concession->price_minor_units, strtoupper((string) $concession->currency))->format() }} · {{ $concession->stock === null ? __('booking.combos.unlimited') : __('booking.combos.stock', ['count' => $concession->stock]) }}</span><span class="mt-1 block text-xs text-primary" data-combo-quantity-status data-selected-label="{{ __('booking.combos.selected_quantity') }}" data-available-label="{{ $maxQuantity }}"></span></span>
                    <input type="number" min="0" max="{{ $maxQuantity }}" inputmode="numeric" name="quantities[{{ $concession->id }}]" value="{{ old('quantities.'.$concession->id, $selectedQuantity) }}" aria-label="{{ __('booking.combos.quantity_label', ['name' => $concession->name]) }}" data-combo-price="{{ $concession->price_minor_units }}" data-combo-stock="{{ $concession->stock ?? '' }}" class="w-20 rounded-lg border border-border bg-card px-3 py-2 text-center">
                </label>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('booking.combos.empty') }}</p>
            @endforelse

            <div class="flex flex-col gap-4 border-t border-border pt-6 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-semibold"><span data-combo-count>0</span> {{ __('booking.combos.items_selected') }} · {{ __('booking.combos.total') }}: <span data-combo-total>0 {{ $booking->pricing_currency ?? $booking->currency }}</span></p>
                <x-admin.button type="submit" icon="save">{{ __('booking.combos.save') }}</x-admin.button>
            </div>
        </form>
    </div>
</x-layouts.storefront>
