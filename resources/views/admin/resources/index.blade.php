<x-layouts.auth :title="__('booking.admin.resources_title')">
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ __('booking.admin.resources_title') }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('booking.admin.resources_description') }}</p>
            </div><x-admin.button :href="route('admin.resources.create')"
                icon="plus">{{ __('booking.admin.create_resource') }}</x-admin.button>
        </div>
        @if (session('status'))
            <div role="status"
                class="rounded-xl border border-success/30 bg-success-soft p-4 text-sm text-success-foreground">
        {{ __(session('status')) }}</div>@endif
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-4">{{ __('booking.admin.name') }}</th>
                            <th class="px-5 py-4">{{ __('booking.admin.slug') }}</th>
                            <th class="px-5 py-4">{{ __('booking.admin.timezone') }}</th>
                            <th class="px-5 py-4">{{ __('booking.bookings.status') }}</th>
                            <th class="px-5 py-4 text-right">{{ __('booking.bookings.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">@forelse ($resources as $resource)
                        <tr>
                            <td class="px-5 py-4 font-semibold">{{ $resource->name }}</td>
                            <td class="px-5 py-4 text-muted-foreground">{{ $resource->slug }}</td>
                            <td class="px-5 py-4 text-muted-foreground">{{ $resource->timezone }}</td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold">{{ $resource->is_active ? __('booking.admin.active') : __('booking.admin.inactive') }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2"><x-admin.button :href="route('admin.resources.edit', $resource)" variant="secondary" compact
                                        icon="edit">{{ __('ui.actions.edit') }}</x-admin.button>
                                    <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}"
                                        onsubmit="return confirm('{{ __('booking.admin.delete_confirm') }}')">@csrf
                                        @method('DELETE')<x-admin.button type="submit" variant="danger" compact
                                            icon="trash">{{ __('booking.admin.delete') }}</x-admin.button></form>
                                </div>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-muted-foreground">
                                {{ __('booking.resources.empty') }}</td>
                        </tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $resources->links() }}</div>
    </div>
</x-layouts.auth>
