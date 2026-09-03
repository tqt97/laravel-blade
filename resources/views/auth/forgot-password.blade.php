<x-layouts.guest title="Quên mật khẩu">
    <div class="mb-8">
        <a href="{{ route('login') }}"
            class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">←
            Quay lại đăng nhập
        </a>
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">
            Khôi phục quyền truy cập</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">
            Bạn quên mật khẩu?</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">
            Nhập email đã đăng ký. Chúng tôi sẽ gửi
            cho bạn liên kết để đặt lại mật khẩu.
        </p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-auth.input label="Địa chỉ email" name="email" type="email" :value="old('email')" autocomplete="email"
            placeholder="you@example.com" required autofocus />
        <x-admin.button type="submit" icon="arrow-right" class="w-full">Gửi liên kết đặt lại</x-admin.button>
    </form>
</x-layouts.guest>
