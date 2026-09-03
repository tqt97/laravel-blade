<x-layouts.guest :title="__('ui.auth_pages.new_account')" wide>
    <div class="mb-6">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">
            {{ __('ui.auth_pages.start_today') }}

        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">
            {{ __('ui.auth_pages.new_account') }}

        </h1>
        <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-neutral-400">
            {{ __('ui.auth_pages.register_description') }}
        </p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-auth.input :label="__('ui.auth_pages.full_name')" name="name" :value="old('name')" autocomplete="name"
                :placeholder="__('ui.auth_pages.name_placeholder')" required autofocus />

            <x-auth.input :label="__('ui.auth_pages.email')" name="email" type="email" :value="old('email')" autocomplete="email"
                placeholder="you@example.com" required />

        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-auth.password-input :label="__('ui.auth_pages.password')" name="password" autocomplete="new-password"
                :hint="__('ui.auth_pages.password_hint')" generate required />

            <x-auth.password-input :label="__('ui.auth_pages.confirm_password')" name="password_confirmation" autocomplete="new-password"
                required />

        </div>
        <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.auth_pages.sign_up') }}</x-admin.button>
    </form>

    <div class="my-7 flex items-center gap-4">
        <div class="h-px flex-1 bg-neutral-200 dark:bg-white/10"></div><span
            class="text-xs font-medium uppercase tracking-wider text-neutral-400">
            {{ __('ui.auth_pages.sign_up_with') }}
        </span>
        <div class="h-px flex-1 bg-neutral-200 dark:bg-white/10"></div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-auth.social-login provider="google" label="Google" route-name="auth.google" />
        <x-auth.social-login provider="github" label="GitHub" route-name="auth.github" />
    </div>
    <p class="mt-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
        {{ __('ui.auth_pages.has_account') }}
        <a href="{{ route('login') }}"
            class="font-semibold text-neutral-600 transition hover:text-neutral-700 dark:text-neutral-300 dark:hover:text-neutral-200">
            {{ __('ui.auth_pages.login_title') }}
        </a>
    </p>
</x-layouts.guest>
