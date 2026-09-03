<x-layouts.guest title="Tạo tài khoản" wide>
    <div class="mb-6">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">
            Bắt đầu ngay hôm nay

        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">
            Tạo tài khoản mới

        </h1>
        <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-neutral-400">
            Thiết lập tài khoản để bắt đầu học và xây dựng với Laravel.
        </p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-auth.input label="Họ và tên" name="name" :value="old('name')" autocomplete="name"
                placeholder="Nguyễn Văn A" required autofocus />

            <x-auth.input label="Địa chỉ email" name="email" type="email" :value="old('email')" autocomplete="email"
                placeholder="you@example.com" required />

        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-auth.password-input label="Mật khẩu" name="password" autocomplete="new-password"
                hint="Tối thiểu 8 ký tự." generate required />

            <x-auth.password-input label="Xác nhận mật khẩu" name="password_confirmation" autocomplete="new-password"
                required />

        </div>
        <x-admin.button type="submit" icon="arrow-right" class="w-full">Tạo tài khoản</x-admin.button>
    </form>

    <div class="my-7 flex items-center gap-4">
        <div class="h-px flex-1 bg-neutral-200 dark:bg-white/10"></div><span
            class="text-xs font-medium uppercase tracking-wider text-neutral-400">
            Hoặc đăng ký với
        </span>
        <div class="h-px flex-1 bg-neutral-200 dark:bg-white/10"></div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-auth.social-login provider="google" label="Google" route-name="auth.google" />
        <x-auth.social-login provider="github" label="GitHub" route-name="auth.github" />
    </div>
    <p class="mt-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
        Đã có tài khoản?
        <a href="{{ route('login') }}"
            class="font-semibold text-neutral-600 transition hover:text-neutral-700 dark:text-neutral-300 dark:hover:text-neutral-200">
            Đăng nhập
        </a>
    </p>
</x-layouts.guest>
