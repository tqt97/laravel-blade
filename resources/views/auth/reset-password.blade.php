<x-layouts.guest :title="__('ui.auth_pages.reset_password')">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">{{ __('ui.security.account_title') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">{{ __('ui.auth_pages.reset_password') }}</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ __('ui.auth_pages.reset_password_description') }}</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-auth.input :label="__('ui.auth_pages.email')" name="email" type="email" :value="old('email', $request->email)"
            autocomplete="email" required autofocus />
        <x-auth.password-input :label="__('ui.auth_pages.new_password')" name="password" autocomplete="new-password"
            :hint="__('ui.auth_pages.password_hint')" generate required />

        <x-auth.password-input :label="__('ui.auth_pages.confirm_password')" name="password_confirmation" autocomplete="new-password"
            required />

        <x-admin.button type="submit" icon="save" class="w-full">{{ __('ui.actions.save') }}</x-admin.button>
    </form>
</x-layouts.guest>
