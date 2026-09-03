<x-layouts.guest :title="__('ui.auth_pages.login_verification')">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">{{ __('ui.auth_pages.security_step') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">{{ __('ui.auth_pages.login_verification') }}</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ __('ui.auth_pages.two_factor_description') }}</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label for="code" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">
                {{ __('ui.auth_pages.verification_code') }}
            </label>

            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-4 py-3.5 text-center text-2xl font-semibold tracking-[0.5em] text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-500 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/20 dark:bg-white/6 dark:text-white dark:placeholder:text-neutral-500"
                placeholder="000000" autofocus>
        </div>

        <div class="relative flex items-center py-1">
            <div class="grow border-t border-neutral-200 dark:border-white/10"></div><span
                class="mx-4 shrink text-xs text-neutral-500 dark:text-neutral-400">{{ __('ui.auth_pages.or') }}</span>
            <div class="grow border-t border-neutral-200 dark:border-white/10"></div>
        </div>
        <div>
            <label for="recovery_code" class="mb-2 block text-sm font-medium text-neutral-700 dark:text-neutral-200">{{ __('ui.auth_pages.recovery_code') }}</label>

            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                class="block w-full rounded-lg border border-neutral-300 bg-white px-4 py-3 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-500 focus:ring-4 focus:ring-neutral-500/10 dark:border-white/20 dark:bg-white/6 dark:text-white dark:placeholder:text-neutral-500"
                :placeholder="__('ui.auth_pages.recovery_placeholder')">
        </div>

        <label class="flex items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400"><input type="checkbox"
                name="remember"
                class="size-4 rounded border-neutral-300 bg-white text-neutral-500 focus:ring-neutral-400/30 dark:border-white/20 dark:bg-white/10 dark:text-neutral-400">
            {{ __('ui.auth_pages.remember') }}
        </label>
        <x-admin.button type="submit" icon="arrow-right" class="w-full">
            {{ __('ui.auth_pages.confirm_login') }}
        </x-admin.button>
    </form>
</x-layouts.guest>
