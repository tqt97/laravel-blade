<x-layouts.guest :title="__('ui.auth_pages.forgot_password')">
    <div class="mb-8">
        <a href="{{ route('login') }}"
            class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-muted-foreground transition hover:text-foreground">←
            {{ __('ui.auth_pages.back_to_login') }}
        </a>
        <p class="mb-3 text-sm font-semibold text-muted-foreground">
            {{ __('ui.auth_pages.recover_access') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-foreground">
            {{ __('ui.auth_pages.forgot_password') }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">
            {{ __('ui.auth_pages.reset_description') }}
        </p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-auth.input :label="__('ui.auth_pages.email')" name="email" type="email" :value="old('email')" autocomplete="email"
            placeholder="you@example.com" required autofocus />
        <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.auth_pages.send_reset_link') }}</x-admin.button>
    </form>
</x-layouts.guest>
