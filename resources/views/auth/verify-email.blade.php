<x-layouts.guest :title="__('ui.auth_pages.verify_email')">
    <div class="mb-8">
        <p class="mb-3 text-sm font-semibold text-neutral-600 dark:text-neutral-300">{{ __('ui.auth_pages.last_step') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">{{ __('ui.auth_pages.verify_email') }}</h1>
        <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ __('ui.auth_pages.verify_email_description') }}</p>
    </div>

    <x-auth.feedback />

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-5">
        @csrf
        <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.auth_pages.resend_verification') }}</x-admin.button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit"
            class="text-sm font-semibold text-neutral-500 transition hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white">{{ __('ui.app.logout') }}</button>
    </form>
</x-layouts.guest>
