<x-layouts.guest :title="__('ui.auth_pages.login_title')">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-muted-foreground">
            {{ __('ui.auth_pages.welcome_back') }}
        </p>
        <h1 class="text-3xl font-semibold tracking-tight text-foreground">
            {{ __('ui.auth_pages.login_title') }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">
            {{ __('ui.auth_pages.login_description') }}
        </p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <x-auth.input :label="__('ui.auth_pages.email')" name="email" type="email" :value="old('email')" autocomplete="email"
            placeholder="you@example.com" required autofocus />

        <x-auth.password-input :label="__('ui.auth_pages.password')" name="password" :action-text="__('ui.auth_pages.forgot_link')"
            action-href="{{ route('password.request') }}" autocomplete="current-password" required />

        <label class="flex items-center gap-3 text-sm text-muted-foreground">
            <input type="checkbox" name="remember"
                class="size-4 rounded border-input bg-card text-primary focus:ring-primary/30">
            {{ __('ui.auth_pages.remember_device') }}
        </label>

        <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.auth_pages.login_title') }}</x-admin.button>
    </form>

    <div class="my-7 flex items-center gap-4">
        <div class="h-px flex-1 bg-border"></div><span
            class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
            {{ __('ui.auth_pages.continue_with') }}
        </span>
        <div class="h-px flex-1 bg-border"></div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <x-auth.social-login provider="google" label="Google" route-name="auth.google" />
        <x-auth.social-login provider="github" label="GitHub" route-name="auth.github" />
    </div>

    <p class="mt-8 text-center text-sm text-muted-foreground">{{ __('ui.auth_pages.no_account') }}
        <a href="{{ route('register') }}"
            class="font-semibold text-primary transition hover:text-primary-strong">
            {{ __('ui.auth_pages.create_account') }}
        </a>
    </p>
</x-layouts.guest>
