<x-layouts.guest title="Đặt lại mật khẩu">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">Bảo mật tài khoản</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">Đặt lại mật khẩu</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Chọn một mật khẩu mới để tiếp tục sử
            dụng tài khoản an toàn.</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-auth.input label="Địa chỉ email" name="email" type="email" :value="old('email', $request->email)"
            autocomplete="email" required autofocus />
        <x-auth.password-input label="Mật khẩu mới" name="password" autocomplete="new-password"
            hint="Tối thiểu 8 ký tự." generate required />

        <x-auth.password-input label="Xác nhận mật khẩu mới" name="password_confirmation" autocomplete="new-password"
            required />

        <x-admin.button type="submit" icon="save" class="w-full">Cập nhật mật khẩu</x-admin.button>
    </form>
</x-layouts.guest>
