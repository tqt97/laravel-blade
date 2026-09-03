<x-layouts.guest title="Xác nhận mật khẩu">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">Xác nhận danh tính</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">Nhập lại mật khẩu</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Vui lòng xác nhận mật khẩu trước khi
            tiếp tục đến khu vực bảo mật.</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf
        <x-auth.password-input label="Mật khẩu hiện tại" name="password" autocomplete="current-password" required
            autofocus />
        <x-admin.button type="submit" icon="arrow-right" class="w-full">Xác nhận</x-admin.button>
    </form>
</x-layouts.guest>
