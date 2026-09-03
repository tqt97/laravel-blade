@php
    $user = auth()->user();
    $twoFactorEnabled = $user->hasEnabledTwoFactorAuthentication();
    $twoFactorPendingConfirmation = ! is_null($user->two_factor_secret) && ! $twoFactorEnabled;
@endphp

<x-layouts.auth title="Bảo mật" heading="Bảo mật">
    <x-slot:breadcrumbs>
        <x-admin.breadcrumbs :items="[['label' => 'Cài đặt bảo mật']]" />
    </x-slot:breadcrumbs>

    <div class="mx-auto max-w-4xl space-y-6">
        <x-admin.page-header title="Bảo mật tài khoản"
            description="Quản lý xác thực hai bước và các mã khôi phục cho tài khoản của bạn." />

        <x-auth.feedback />

        @if (! $twoFactorEnabled && ! $twoFactorPendingConfirmation)
            <section class="rounded-2xl border border-neutral-200/80 bg-white shadow-sm shadow-neutral-200/40 dark:border-white/10 dark:bg-white/[0.03] dark:shadow-none">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700 dark:bg-white/10 dark:text-neutral-200">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect width="14" height="18" x="5" y="3" rx="2"/><path d="M8 7h8M8 11h8M8 15h4"/></svg>
                        </span>
                        <div>
                            <h2 class="font-semibold text-neutral-950 dark:text-white">Xác thực hai bước</h2>
                            <p class="mt-1 max-w-xl text-sm leading-6 text-neutral-500 dark:text-neutral-400">Bảo vệ tài khoản bằng mã xác thực từ ứng dụng như Google Authenticator, 1Password hoặc Authy.</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="shrink-0">
                        @csrf
                        <x-admin.button type="submit" icon="arrow-right">Bật 2FA</x-admin.button>
                    </form>
                </div>
            </section>
        @elseif ($twoFactorPendingConfirmation)
            <section class="rounded-2xl border border-amber-200 bg-white shadow-sm shadow-neutral-200/40 dark:border-amber-400/20 dark:bg-white/[0.03] dark:shadow-none">
                <div class="border-b border-amber-100 p-6 dark:border-amber-400/15">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">Hoàn tất thiết lập</p>
                    <h2 class="mt-2 font-semibold text-neutral-950 dark:text-white">Quét mã QR để bật 2FA</h2>
                    <p class="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Mở ứng dụng xác thực, quét mã bên dưới rồi nhập mã 6 chữ số để xác nhận.</p>
                </div>
                <div class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center">
                    <div class="flex size-52 items-center justify-center rounded-xl border border-neutral-200 bg-white p-3 dark:border-white/15">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900 dark:text-white">Không thể quét mã?</p>
                        <p class="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Nhập khóa thiết lập thủ công vào ứng dụng xác thực.</p>
                        <code class="mt-3 block break-all rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm font-semibold tracking-wider text-neutral-800 dark:border-white/15 dark:bg-white/[0.05] dark:text-neutral-200">{{ decrypt($user->two_factor_secret) }}</code>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                            @csrf
                            <div class="min-w-0 flex-1"><label for="two-factor-code" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">Mã xác nhận</label><input id="two-factor-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="block w-full rounded-lg border border-neutral-300 bg-white px-4 py-2.5 text-sm tracking-[0.3em] text-neutral-900 outline-none transition focus:border-neutral-500 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/20 dark:bg-white/[0.06] dark:text-white" placeholder="000000"></div>
                            <x-admin.button type="submit" icon="save">Xác nhận</x-admin.button>
                        </form>
                    </div>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-emerald-200 bg-white shadow-sm shadow-neutral-200/40 dark:border-emerald-400/20 dark:bg-white/[0.03] dark:shadow-none">
                <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300"><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 6v5c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V6l-8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
                        <div><h2 class="font-semibold text-neutral-950 dark:text-white">2FA đang được bật</h2><p class="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Tài khoản của bạn cần mã xác thực khi đăng nhập trên thiết bị mới.</p></div>
                    </div>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="shrink-0">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger" icon="trash">Tắt 2FA</x-admin.button></form>
                </div>
                <div class="border-t border-emerald-100 p-6 dark:border-emerald-400/15">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-semibold text-neutral-950 dark:text-white">Mã khôi phục</h3><p class="mt-1 text-sm leading-6 text-neutral-500 dark:text-neutral-400">Lưu các mã này ở nơi an toàn. Mỗi mã chỉ dùng được một lần.</p></div><form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">@csrf<x-admin.button type="submit" variant="secondary" icon="arrow-right">Tạo mã mới</x-admin.button></form></div>
                    <div class="mt-4 grid gap-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 font-mono text-sm text-neutral-700 sm:grid-cols-2 dark:border-white/15 dark:bg-white/[0.04] dark:text-neutral-200">@foreach ($user->recoveryCodes() as $recoveryCode)<code>{{ $recoveryCode }}</code>@endforeach</div>
                </div>
            </section>
        @endif
    </div>
</x-layouts.auth>
