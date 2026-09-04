@php
    $hasFilters = filled(request('search'))
        || in_array(request('verification'), ['verified', 'unverified'], true)
        || in_array(request('role'), ['admin', 'user'], true)
        || in_array(request('two_factor'), ['enabled', 'disabled'], true)
        || in_array(request('status'), ['all', 'deleted'], true)
        || ((int) request('per_page', 15) !== 15);
    $status = session('status');
    $isDeletedView = request('status', 'active') === 'deleted';
@endphp

<x-layouts.auth :title="__('ui.users.title')" :heading="__('ui.users.title')">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => __('ui.users.title')]]" />
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header :title="__('ui.users.management')" :description="__('ui.users.management_description')">
            <x-slot:actions>
                <x-admin.button href="{{ route('admin.users.create') }}" icon="plus">
                    {{ __('ui.users.add') }}
                </x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        @if ($status)
            <div class="rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-medium text-success-foreground" role="status">
                {{ is_array($status) ? __($status['key'], $status['replace'] ?? []) : __($status) }}
            </div>
        @endif

        <x-admin.table-shell :title="__('ui.users.all')" :description="__('ui.users.found', ['count' => number_format($users->total())])">
            <x-slot:actions>
                <div data-bulk-actions class="relative hidden">
                    <button type="button" data-bulk-actions-trigger aria-expanded="false" aria-controls="user-bulk-actions-menu" class="ui-action inline-flex min-h-9 items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-card-foreground shadow-sm transition hover:border-primary hover:bg-accent focus:ring-4 focus:ring-primary/15">
                        {{ __('ui.bulk_actions') }}
                        <svg data-bulk-actions-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div id="user-bulk-actions-menu" data-bulk-actions-menu hidden class="absolute right-0 top-[calc(100%+0.625rem)] z-30 w-64 space-y-1 rounded-xl border border-border bg-popover p-2 shadow-lg">
                        @if (! $isDeletedView)
                            <x-admin.button variant="danger" type="button" icon="trash" class="w-full justify-start whitespace-nowrap" data-bulk-action data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.bulk-destroy') }}" data-modal-method="DELETE" data-modal-title="{{ __('ui.users.delete_title') }}" data-modal-description="{{ __('ui.users.delete_description') }}" data-modal-confirm-label="{{ __('ui.users.delete_confirm') }}">{{ __('ui.users.delete_selected') }}</x-admin.button>
                        @endif
                        @if ($isDeletedView || request('status') === 'all')
                            <x-admin.button variant="secondary" type="button" icon="restore" class="w-full justify-start whitespace-nowrap" data-bulk-action data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.bulk-restore') }}" data-modal-method="PATCH" data-modal-title="{{ __('ui.users.restore_title') }}" data-modal-description="{{ __('ui.users.restore_description') }}" data-modal-confirm-label="{{ __('ui.users.restore_confirm') }}">{{ __('ui.users.restore_selected') }}</x-admin.button>
                            <x-admin.button variant="danger" type="button" icon="trash" class="w-full justify-start whitespace-nowrap" data-bulk-action data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.bulk-force-delete') }}" data-modal-method="DELETE" data-modal-title="{{ __('ui.users.force_delete_title') }}" data-modal-description="{{ __('ui.users.force_delete_description') }}" data-modal-confirm-label="{{ __('ui.users.force_delete_confirm') }}">{{ __('ui.users.force_delete_selected') }}</x-admin.button>
                        @endif
                    </div>
                </div>
                @if ($hasFilters)
                    <a href="{{ route('admin.users.index') }}" class="ui-action rounded-lg px-3 py-2 text-xs font-semibold text-muted-foreground transition hover:bg-accent hover:text-foreground">{{ __('ui.users.clear_filters') }}</a>
                @endif
            </x-slot:actions>

            @include('admin.users._filters')

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-left text-sm">
                    <thead class="bg-muted text-xs uppercase tracking-wider text-muted-foreground"><tr><th class="w-12 px-5 py-3"><input type="checkbox" data-user-select-all aria-label="{{ __('ui.users.select_all') }}" class="size-4 cursor-pointer rounded border-input text-primary accent-primary"></th><th class="px-5 py-3 font-semibold">{{ __('ui.users.user') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.role') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.verification') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.two_factor') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.joined') }}</th><th class="px-5 py-3 text-right font-semibold">{{ __('ui.users.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($users as $user)
                            @include('admin.users._row', ['user' => $user])
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-muted-foreground">{{ __('ui.users.no_match') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())<div class="border-t border-border p-5">{{ $users->links() }}</div>@endif
        </x-admin.table-shell>
    </div>

    <x-admin.confirm-modal id="delete-user-modal" :title="__('ui.users.delete_title')" :description="__('ui.users.delete_description')" :confirm-label="__('ui.users.delete_confirm')" />
</x-layouts.auth>
