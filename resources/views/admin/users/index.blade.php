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
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300" role="status">
                {{ is_array($status) ? __($status['key'], $status['replace'] ?? []) : __($status) }}
            </div>
        @endif

        <x-admin.table-shell :title="__('ui.users.all')" :description="__('ui.users.found', ['count' => number_format($users->total())])">
            <x-slot:actions>
                <div data-bulk-actions class="relative hidden">
                    <button type="button" data-bulk-actions-trigger aria-expanded="false" aria-controls="user-bulk-actions-menu" class="ui-action inline-flex min-h-9 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:border-neutral-400 hover:bg-neutral-50 focus:outline-none focus:ring-4 focus:ring-neutral-500/10 dark:border-white/20 dark:bg-white/[0.06] dark:text-neutral-200 dark:hover:border-white/30 dark:hover:bg-white/10">
                        {{ __('ui.bulk_actions') }}
                        <svg data-bulk-actions-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div id="user-bulk-actions-menu" data-bulk-actions-menu hidden class="absolute right-0 top-[calc(100%+0.625rem)] z-30 w-64 space-y-1 rounded-xl border border-neutral-200 bg-white p-2 shadow-xl shadow-neutral-900/10 dark:border-white/15 dark:bg-neutral-900 dark:shadow-black/30">
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
                    <a href="{{ route('admin.users.index') }}" class="ui-action rounded-lg px-3 py-2 text-xs font-semibold text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-white/10 dark:hover:text-white">{{ __('ui.users.clear_filters') }}</a>
                @endif
            </x-slot:actions>

            <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 border-b border-neutral-100 bg-neutral-50/60 p-5 sm:grid-cols-2 xl:grid-cols-[minmax(16rem,1.5fr)_repeat(4,minmax(9rem,1fr))_auto] dark:border-white/10 dark:bg-white/[0.02]">
                <label class="sm:col-span-2 xl:col-span-1"><span class="sr-only">{{ __('ui.users.search') }}</span><input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('ui.users.search_placeholder') }}" class="block w-full rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-white dark:placeholder:text-neutral-500"></label>
                <select name="verification" class="ui-action rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-700 outline-none focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-neutral-200"><option value="all">{{ __('ui.users.all_verification') }}</option><option value="verified" @selected(request('verification') === 'verified')>{{ __('ui.users.verified') }}</option><option value="unverified" @selected(request('verification') === 'unverified')>{{ __('ui.users.unverified') }}</option></select>
                <select name="role" class="ui-action rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-700 outline-none focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-neutral-200"><option value="all">{{ __('ui.users.all_roles') }}</option><option value="admin" @selected(request('role') === 'admin')>{{ __('ui.users.admin') }}</option><option value="user" @selected(request('role') === 'user')>{{ __('ui.users.user') }}</option></select>
                <select name="two_factor" class="ui-action rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-700 outline-none focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-neutral-200"><option value="all">{{ __('ui.users.all_2fa') }}</option><option value="enabled" @selected(request('two_factor') === 'enabled')>{{ __('ui.users.enabled') }}</option><option value="disabled" @selected(request('two_factor') === 'disabled')>{{ __('ui.users.disabled') }}</option></select>
                <select name="status" class="ui-action rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-700 outline-none focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-neutral-200"><option value="active" @selected(request('status', 'active') === 'active')>{{ __('ui.users.active') }}</option><option value="all" @selected(request('status') === 'all')>{{ __('ui.users.all_statuses') }}</option><option value="deleted" @selected(request('status') === 'deleted')>{{ __('ui.users.deleted') }}</option></select>
                <select name="per_page" class="ui-action rounded-md border border-neutral-400 bg-white px-3 py-2 text-sm text-neutral-700 outline-none focus:border-neutral-600 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/25 dark:bg-white/[0.06] dark:text-neutral-200">@foreach ([15, 30, 50] as $pageSize)<option value="{{ $pageSize }}" @selected((int) request('per_page', 15) === $pageSize)>{{ __('ui.users.per_page', ['count' => $pageSize]) }}</option>@endforeach</select>
                <x-admin.button type="submit" icon="arrow-right" compact>{{ __('ui.users.apply') }}</x-admin.button>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-100 text-left text-sm dark:divide-white/10">
                    <thead class="bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:bg-white/[0.03] dark:text-neutral-400"><tr><th class="w-12 px-5 py-3"><input type="checkbox" data-user-select-all aria-label="{{ __('ui.users.select_all') }}" class="size-4 cursor-pointer rounded border-neutral-400 text-neutral-900 accent-neutral-900"></th><th class="px-5 py-3 font-semibold">{{ __('ui.users.user') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.role') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.verification') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.two_factor') }}</th><th class="px-5 py-3 font-semibold">{{ __('ui.users.joined') }}</th><th class="px-5 py-3 text-right font-semibold">{{ __('ui.users.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-white/10">
                        @forelse ($users as $user)
                            <tr class="transition hover:bg-neutral-50/80 dark:hover:bg-white/[0.03]"><td class="px-5 py-4"><input type="checkbox" value="{{ $user->id }}" data-user-selection aria-label="{{ __('ui.users.select_user', ['name' => $user->name]) }}" class="size-4 cursor-pointer rounded border-neutral-400 text-neutral-900 accent-neutral-900"></td><td class="whitespace-nowrap px-5 py-4"><p class="font-medium text-neutral-900 dark:text-white">{{ $user->name }}</p><p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $user->email }}</p></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_admin ? 'bg-violet-100 text-violet-700' : 'bg-neutral-100 text-neutral-700' }}">{{ $user->is_admin ? __('ui.users.admin') : __('ui.users.user') }}</span></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ $user->email_verified_at ? __('ui.users.verified') : __('ui.users.unverified') }}</span></td><td class="px-5 py-4 text-neutral-500">{{ $user->two_factor_confirmed_at ? __('ui.users.enabled') : __('ui.users.disabled') }}</td><td class="whitespace-nowrap px-5 py-4 text-neutral-500">{{ $user->trashed() ? __('ui.users.deleted') : $user->created_at?->format('M d, Y') }}</td><td class="px-5 py-4"><div class="flex justify-end gap-1">
                                @if ($user->trashed())
                                    <x-admin.button variant="secondary" type="button" icon="restore" icon-only :title="__('ui.users.restore')" aria-label="{{ __('ui.users.restore_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.restore', $user->id) }}" data-modal-method="PATCH" data-modal-title="{{ __('ui.users.restore_title') }}" data-modal-description="{{ __('ui.users.restore_description') }}" data-modal-confirm-label="{{ __('ui.users.restore_confirm') }}" />
                                    <x-admin.button variant="danger" type="button" icon="trash" icon-only :title="__('ui.users.force_delete')" aria-label="{{ __('ui.users.force_delete_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.force-delete', $user->id) }}" data-modal-method="DELETE" data-modal-title="{{ __('ui.users.force_delete_title') }}" data-modal-description="{{ __('ui.users.force_delete_description') }}" data-modal-confirm-label="{{ __('ui.users.force_delete_confirm') }}" />
                                @else
                                    <x-admin.button variant="ghost" href="{{ route('admin.users.edit', $user) }}" icon="pencil" icon-only :title="__('ui.users.edit')" aria-label="{{ __('ui.users.edit_user', ['name' => $user->name]) }}" />
                                    <x-admin.button variant="danger" type="button" icon="trash" icon-only :title="__('ui.users.delete')" aria-label="{{ __('ui.users.delete_user', ['name' => $user->name]) }}" data-modal-open="delete-user-modal" data-modal-action="{{ route('admin.users.destroy', $user) }}" data-modal-method="DELETE" data-modal-title="{{ __('ui.users.delete_title') }}" data-modal-description="{{ __('ui.users.delete_description') }}" data-modal-confirm-label="{{ __('ui.users.delete_confirm') }}" />
                                @endif
                            </div></td></tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-neutral-500">{{ __('ui.users.no_match') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())<div class="border-t border-neutral-100 p-5 dark:border-white/10">{{ $users->links() }}</div>@endif
        </x-admin.table-shell>
    </div>

    <x-admin.confirm-modal id="delete-user-modal" :title="__('ui.users.delete_title')" :description="__('ui.users.delete_description')" :confirm-label="__('ui.users.delete_confirm')" />
</x-layouts.auth>
