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
            <table class="min-w-full divide-y divide-border text-left text-sm">
                <thead
                    class="bg-muted text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.students') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('ui.samples.updated') }}</th>
                        <th class="px-5 py-3 text-right font-semibold">{{ __('ui.samples.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ([['name' => 'Nguyễn Minh Anh', 'email' => 'minhanh@example.com', 'status' => __('ui.samples.studying'), 'date' => __('ui.samples.today')], ['name' => 'Trần Quốc Bảo', 'email' => 'quocbao@example.com', 'status' => __('ui.samples.pending'), 'date' => __('ui.samples.yesterday')]] as $student)
                        <tr class="transition hover:bg-accent/50">
                            <td class="whitespace-nowrap px-5 py-4">
                                <p class="font-medium text-card-foreground">{{ $student['name'] }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ $student['email'] }}</p>
                            </td>
                            <td class="px-5 py-4"><span
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">{{ $student['status'] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-muted-foreground">
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
