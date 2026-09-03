<x-layouts.auth title="New page" heading="New page">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => 'Trang mới']]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header title="New page" description="A starting template for new admin modules.">
            <x-slot:actions>
                <x-admin.button variant="secondary" disabled>No actions yet</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.blank-state title="Bắt đầu xây dựng module"
            description="Thay thế trạng thái này bằng table-shell, form-shell hoặc nội dung riêng của module." />
    </div>
</x-layouts.auth>
