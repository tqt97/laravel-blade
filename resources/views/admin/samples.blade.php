<x-layouts.auth>
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => 'UI Samples']]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header :title="__('ui.samples.title')" :description="__('ui.samples.description')">
            <x-slot:actions>
                <x-admin.button type="button" data-modal-open="sample-confirm">{{ __('ui.samples.open_modal') }}</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.table-shell :title="__('ui.samples.students')" :description="__('ui.samples.table_description')">
            <x-slot:actions>
                <x-admin.button variant="secondary" icon="plus" href="#sample-form">{{ __('ui.samples.add') }}</x-admin.button>
            </x-slot:actions>
            <table class="min-w-full divide-y divide-neutral-100 text-left text-sm dark:divide-white/10">
                <thead
                    class="bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:bg-white/3 dark:text-neutral-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.students') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.updated') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('ui.samples.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-white/10">
                    @foreach ([['name' => 'Nguyễn Minh Anh', 'email' => 'minhanh@example.com', 'status' => __('ui.samples.studying'), 'date' => __('ui.samples.today')], ['name' => 'Trần Quốc Bảo', 'email' => 'quocbao@example.com', 'status' => __('ui.samples.pending'), 'date' => __('ui.samples.yesterday')]] as $student)
                        <tr class="transition hover:bg-neutral-50/80 dark:hover:bg-white/3">
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-medium text-neutral-900 dark:text-white">{{ $student['name'] }}</p>
                                <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $student['email'] }}</p>
                            </td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-700 dark:bg-white/10 dark:text-neutral-300">{{ $student['status'] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-neutral-500 dark:text-neutral-400">
                                {{ $student['date'] }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-1"><x-admin.button variant="ghost" type="button"
                                        icon="pencil" icon-only :title="__('ui.samples.edit')"
                                        aria-label="{{ __('ui.users.edit_user', ['name' => $student['name']]) }}" /><x-admin.button variant="danger"
                                        type="button" icon="trash" icon-only :title="__('ui.samples.delete')"
                                        aria-label="{{ __('ui.users.delete_user', ['name' => $student['name']]) }}" data-modal-open="sample-confirm" /></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin.table-shell>

        <div id="sample-form" class="grid gap-6 xl:grid-cols-2">
            <x-admin.form-shell :title="__('ui.samples.form_title')"
                :description="__('ui.samples.form_description')" :submit-label="__('ui.samples.save_student')"
                submit-icon="save">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-auth.input :label="__('ui.auth_pages.full_name')" name="sample_name" :placeholder="__('ui.auth_pages.name_placeholder')" />
                    <x-auth.input :label="__('ui.auth_pages.email')" name="sample_email" type="email" placeholder="you@example.com" />
                </div>
                <x-auth.input :label="__('ui.samples.description')" name="sample_description" :hint="__('ui.samples.max_chars')" />
            </x-admin.form-shell>

            <div class="space-y-6">
                <x-admin.blank-state :title="__('ui.samples.blank')"
                    :description="__('ui.samples.blank_description')" />
                <x-admin.toast :message="__('ui.samples.toast')" />
            </div>
        </div>
    </div>

    <x-admin.confirm-modal id="sample-confirm" :title="__('ui.samples.confirm_delete')"
        :description="__('ui.samples.modal_description')" :confirm-label="__('ui.samples.continue')" />
</x-layouts.auth>
