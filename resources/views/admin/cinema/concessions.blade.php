<x-layouts.auth :title="__('cinema.admin.concessions_title')" :heading="__('cinema.admin.concessions_title')">
    <div class="space-y-6">
        <x-admin.page-header :title="__('cinema.admin.concessions_title')" :description="__('cinema.admin.concessions_description')">
            <x-slot:actions>
                <x-admin.button :href="route('admin.cinema.index')" variant="secondary" icon="arrow-left">
                    {{ __('cinema.admin.title') }}
                </x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        @if (session('status'))
            <x-admin.toast :message="__(session('status'))" />
        @endif
        <x-auth.feedback />

        <form method="POST" action="{{ route('admin.cinema.concessions.store') }}" class="grid gap-4 rounded-2xl border border-border bg-card p-5 md:grid-cols-2 xl:grid-cols-6">
            @csrf
            <div class="xl:col-span-2"><x-auth.input :label="__('cinema.admin.concession_name')" name="name" required /></div>
            <x-auth.input :label="__('cinema.admin.concession_sku')" name="sku" required />
            <x-auth.input :label="__('cinema.admin.concession_price')" name="price_minor_units" type="number" min="1" required />
            <x-auth.input :label="__('cinema.admin.currency')" name="currency" value="VND" maxlength="3" required />
            <x-auth.input :label="__('cinema.admin.concession_stock')" name="stock" type="number" min="0" placeholder="∞" />
            <div class="md:col-span-2 xl:col-span-3"><x-auth.input :label="__('cinema.admin.concession_stock_reason')" name="stock_reason" placeholder="{{ __('cinema.admin.concession_stock_reason_placeholder') }}" /></div>
            <div class="md:col-span-2 xl:col-span-3"><x-auth.input :label="__('cinema.admin.concession_image_url')" name="image_url" type="url" placeholder="https://..." /></div>
            <label class="flex items-center gap-2 text-sm font-medium md:col-span-2 xl:col-span-6">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked class="size-4 rounded border-border text-primary focus:ring-primary">
                {{ __('cinema.admin.concession_active') }}
            </label>
            <div class="md:col-span-2 xl:col-span-6"><x-admin.button type="submit" icon="plus">{{ __('cinema.admin.concession_create') }}</x-admin.button></div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_name') }}</th>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_image_url') }}</th>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_sku') }}</th>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_price') }}</th>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_stock') }}</th>
                            <th class="px-5 py-4">{{ __('cinema.admin.concession_active') }}</th>
                            <th class="px-5 py-4 text-right">{{ __('booking.bookings.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($concessions as $concession)
                            <tr>
                                <td class="px-5 py-3"><form id="concession-update-{{ $concession->id }}" method="POST" action="{{ route('admin.cinema.concessions.update', $concession) }}">@csrf @method('PATCH')</form><input form="concession-update-{{ $concession->id }}" name="name" value="{{ old('name', $concession->name) }}" class="w-full min-w-48 rounded-lg border border-border bg-background px-3 py-2" required></td>
                                    <td class="px-5 py-3"><input form="concession-update-{{ $concession->id }}" name="image_url" type="url" value="{{ old('image_url', $concession->image_url) }}" class="w-48 rounded-lg border border-border bg-background px-3 py-2" placeholder="https://..."></td>
                                    <td class="px-5 py-3"><input form="concession-update-{{ $concession->id }}" name="sku" value="{{ old('sku', $concession->sku) }}" class="w-36 rounded-lg border border-border bg-background px-3 py-2" required></td>
                                    <td class="px-5 py-3"><input form="concession-update-{{ $concession->id }}" name="price_minor_units" type="number" min="1" value="{{ old('price_minor_units', $concession->price_minor_units) }}" class="w-36 rounded-lg border border-border bg-background px-3 py-2" required></td>
                                    <td class="px-5 py-3"><input form="concession-update-{{ $concession->id }}" name="stock" type="number" min="0" value="{{ old('stock', $concession->stock) }}" placeholder="∞" class="w-28 rounded-lg border border-border bg-background px-3 py-2"><input form="concession-update-{{ $concession->id }}" name="stock_reason" value="{{ old('stock_reason') }}" class="mt-2 w-40 rounded-lg border border-border bg-background px-3 py-2" placeholder="{{ __('cinema.admin.concession_stock_reason_placeholder') }}"></td>
                                    <td class="px-5 py-3">
                                        <input form="concession-update-{{ $concession->id }}" type="hidden" name="currency" value="{{ $concession->currency }}">
                                        <input form="concession-update-{{ $concession->id }}" type="hidden" name="is_active" value="0">
                                        <label class="inline-flex items-center gap-2"><input form="concession-update-{{ $concession->id }}" type="checkbox" name="is_active" value="1" @checked($concession->is_active) class="size-4 rounded border-border text-primary"> <span class="text-xs">{{ $concession->is_active ? __('ui.app.active') : __('booking.admin.inactive') }}</span></label>
                                    </td>
                                    <td class="px-5 py-3 text-right"><x-admin.button type="submit" form="concession-update-{{ $concession->id }}" variant="secondary" icon="save">{{ __('ui.actions.save') }}</x-admin.button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-10 text-center text-muted-foreground">{{ __('cinema.admin.concessions_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border p-4">{{ $concessions->links() }}</div>
        </div>
    </div>
</x-layouts.auth>
