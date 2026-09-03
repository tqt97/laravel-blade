<x-layouts.guest :title="__('ui.user_dashboard.title')">
    <div class="space-y-6">
        <div>
            <p class="text-sm font-semibold text-neutral-500 dark:text-neutral-400">{{ __('ui.app.workspace') }}</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-neutral-950 dark:text-white">{{ __('ui.user_dashboard.welcome', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-3 text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ __('ui.user_dashboard.description') }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-admin.button type="submit" icon="arrow-right" class="w-full">{{ __('ui.user_dashboard.logout') }}</x-admin.button>
        </form>
    </div>
</x-layouts.guest>
