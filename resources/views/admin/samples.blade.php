<x-layouts.auth>
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => 'UI Samples']]" />
    </x-slot:breadcrumbs>
    <div class="mx-auto max-w-7xl space-y-6">
        <x-admin.page-header title="UI Samples" description="Các mẫu nền tảng để phát triển nhanh module admin mới.">
            <x-slot:actions>
                <x-admin.button type="button" data-modal-open="sample-confirm">Mở modal</x-admin.button>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.table-shell title="Danh sách học viên" description="Mẫu table list với trạng thái và action.">
            <x-slot:actions>
                <x-admin.button variant="secondary" icon="plus" href="#sample-form">Thêm mới</x-admin.button>
            </x-slot:actions>
            <table class="min-w-full divide-y divide-neutral-100 text-left text-sm dark:divide-white/10">
                <thead
                    class="bg-neutral-50 text-xs uppercase tracking-wider text-neutral-500 dark:bg-white/3 dark:text-neutral-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Học viên</th>
                        <th class="px-5 py-3 font-semibold">Trạng thái</th>
                        <th class="px-5 py-3 font-semibold">Cập nhật</th>
                        <th class="px-5 py-3 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-white/10">
                    @foreach ([['name' => 'Nguyễn Minh Anh', 'email' => 'minhanh@example.com', 'status' => 'Đang học', 'date' => 'Hôm nay'], ['name' => 'Trần Quốc Bảo', 'email' => 'quocbao@example.com', 'status' => 'Chờ duyệt', 'date' => 'Hôm qua']] as $student)
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
                                        icon="pencil" icon-only title="Chỉnh sửa"
                                        aria-label="Chỉnh sửa {{ $student['name'] }}" /><x-admin.button variant="danger"
                                        type="button" icon="trash" icon-only title="Xóa"
                                        aria-label="Xóa {{ $student['name'] }}" data-modal-open="sample-confirm" /></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-admin.table-shell>

        <div id="sample-form" class="grid gap-6 xl:grid-cols-2">
            <x-admin.form-shell title="Form create / edit"
                description="Mẫu form dùng chung cho tạo và cập nhật dữ liệu." submit-label="Lưu học viên"
                submit-icon="save">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-auth.input label="Họ và tên" name="sample_name" placeholder="Nguyễn Văn A" />
                    <x-auth.input label="Email" name="sample_email" type="email" placeholder="you@example.com" />
                </div>
                <x-auth.input label="Mô tả" name="sample_description" hint="Tối đa 500 ký tự." />
            </x-admin.form-shell>

            <div class="space-y-6">
                <x-admin.blank-state title="Blank state"
                    description="Dùng khi module chưa có dữ liệu hoặc chưa được triển khai." />
                <x-admin.toast message="Thao tác mẫu đã sẵn sàng." />
            </div>
        </div>
    </div>

    <x-admin.confirm-modal id="sample-confirm" title="Xác nhận xóa"
        description="Đây là modal mẫu. Bạn có thể truyền action thật khi dùng trong module." confirm-label="Tiếp tục" />
</x-layouts.auth>
