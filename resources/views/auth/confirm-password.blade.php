<x-layouts.guest :title="__('ui.auth_pages.enter_password')">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-muted-foreground">{{ __('ui.auth_pages.identity') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-foreground">{{ __('ui.auth_pages.enter_password') }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ __('ui.auth_pages.confirm_password_description') }}</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-5">
        @csrf
        <x-auth.password-input :label="__('ui.auth_pages.current_password')" name="password" autocomplete="current-password" required
            autofocus />
        <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.actions.confirm') }}</x-admin.button>
    </form>
</x-layouts.guest>
