<x-layouts.auth :title="__('ui.users.edit_title')" :heading="__('ui.users.edit_title')">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => __('ui.users.title'), 'href' => route('admin.users.index')], ['label' => __('ui.users.edit_title')]]" />

    </x-slot:breadcrumbs>
    <div class="mx-auto max-w-3xl"><x-admin.page-header :title="__('ui.users.edit_title')"
            :description="__('ui.users.edit_description')" />
        <div class="mt-6">
            @include('admin.users._form', ['action' => route('admin.users.update', $user), 'method' => 'PUT', 'user' => $user])
        </div>
    </div>
</x-layouts.auth>
