<x-layouts.auth :title="__('cinema.reports.title')" :heading="__('cinema.reports.title')">
    <div class="mx-auto max-w-6xl space-y-6">
        <x-admin.page-header :title="__('cinema.reports.title')" :description="__('cinema.reports.description')" />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([['orders', 'cinema.reports.orders'], ['tickets', 'cinema.reports.tickets'], ['checked_in', 'cinema.reports.checked_in'], ['revenue_minor_units', 'cinema.reports.revenue'], ['refunded_minor_units', 'cinema.reports.refunded']] as [$key, $label])
                <x-admin.stat-card :label="__($label)" :value="$summary[$key]" />
            @endforeach
        </div>
    </div>
</x-layouts.auth>
