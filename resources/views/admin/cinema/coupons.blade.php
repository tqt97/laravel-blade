<x-layouts.auth :title="__('cinema.admin.coupons_title')" :heading="__('cinema.admin.coupons_title')">
    <div class="space-y-6">
        <x-auth.feedback />
        <x-admin.page-header :title="__('cinema.admin.coupons_title')" :description="__('cinema.admin.coupons_description')" />
        <form method="POST" action="{{ route('admin.cinema.coupons.store') }}" class="grid gap-4 rounded-2xl border border-border bg-card p-5 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <x-auth.input :label="__('cinema.admin.coupon_code')" name="code" required />
            <div><label for="coupon-type" class="mb-1 block text-sm font-medium">{{ __('cinema.admin.coupon_type') }}</label><select id="coupon-type" name="type" class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm"><option value="percentage">{{ __('cinema.admin.coupon_percentage') }}</option><option value="fixed">{{ __('cinema.admin.coupon_fixed') }}</option></select></div>
            <x-auth.input :label="__('cinema.admin.coupon_value')" name="value" type="number" min="1" required />
            <x-auth.input :label="__('cinema.admin.coupon_usage_limit')" name="usage_limit" type="number" min="1" />
            <x-auth.input :label="__('cinema.admin.coupon_max_discount')" name="maximum_discount_minor_units" type="number" min="1" />
            <x-auth.input :label="__('cinema.admin.coupon_currency')" name="currency" value="VND" maxlength="3" />
            <x-auth.input :label="__('cinema.admin.coupon_starts_at')" name="starts_at" type="datetime-local" />
            <x-auth.input :label="__('cinema.admin.coupon_ends_at')" name="ends_at" type="datetime-local" />
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked class="size-4 rounded border-border text-primary"> {{ __('cinema.admin.coupon_active') }}</label>
            <div class="md:col-span-2 xl:col-span-4"><x-admin.button type="submit" icon="plus">{{ __('cinema.admin.coupon_create') }}</x-admin.button></div>
        </form>
        <div class="overflow-x-auto rounded-2xl border border-border bg-card"><table class="w-full text-left text-sm"><thead class="border-b border-border text-xs uppercase tracking-wide text-muted-foreground"><tr><th class="px-5 py-4">{{ __('cinema.admin.coupon_code') }}</th><th class="px-5 py-4">{{ __('cinema.admin.coupon_type') }}</th><th class="px-5 py-4">{{ __('cinema.admin.coupon_value') }}</th><th class="px-5 py-4">{{ __('cinema.admin.coupon_usage') }}</th><th class="px-5 py-4">{{ __('cinema.admin.coupon_active') }}</th></tr></thead><tbody class="divide-y divide-border">@forelse ($coupons as $coupon)<tr><td class="px-5 py-3 font-semibold">{{ $coupon->code }}</td><td class="px-5 py-3">{{ $coupon->type->value }}</td><td class="px-5 py-3">{{ $coupon->value }}{{ $coupon->type->value === 'percentage' ? '%' : ' '.$coupon->currency }}</td><td class="px-5 py-3">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }}</td><td class="px-5 py-3">{{ $coupon->is_active ? __('ui.app.active') : __('booking.admin.inactive') }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-muted-foreground">{{ __('cinema.admin.coupons_empty') }}</td></tr>@endforelse</tbody></table><div class="border-t border-border p-4">{{ $coupons->links() }}</div></div>
    </div>
</x-layouts.auth>
