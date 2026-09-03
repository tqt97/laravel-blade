@props(['provider', 'label', 'routeName'])

@php($providerRouteAvailable = Route::has($routeName))

@if ($providerRouteAvailable)
    <a href="{{ route($routeName) }}" class="flex items-center justify-center gap-3 rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm font-semibold text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50 focus:outline-none focus:ring-4 focus:ring-neutral-200 dark:border-white/10 dark:bg-white/[0.06] dark:text-neutral-200 dark:hover:border-white/20 dark:hover:bg-white/10 dark:focus:ring-white/10">
@else
    <button type="button" disabled aria-disabled="true" title="{{ __('ui.auth_pages.social_unavailable', ['route' => $routeName, 'provider' => $label]) }}" class="flex cursor-not-allowed items-center justify-center gap-3 rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm font-semibold text-neutral-400 opacity-75 dark:border-white/10 dark:bg-white/[0.06] dark:text-neutral-500">
@endif
        @if ($provider === 'google')
            <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.8 3-4.3 3-7.3Z"/><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.6-2.5l-3.2-2.5c-.9.6-2 .9-3.4.9-2.6 0-4.8-1.8-5.6-4.2H3.1v2.6A10 10 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.4 13.7a6 6 0 0 1 0-3.4V7.7H3.1a10 10 0 0 0 0 8.6l3.3-2.6Z"/><path fill="#EA4335" d="M12 6.1c1.5 0 2.8.5 3.8 1.5l2.8-2.8C17 3.1 14.7 2 12 2a10 10 0 0 0-8.9 5.7l3.3 2.6C7.2 7.9 9.4 6.1 12 6.1Z"/></svg>
        @else
            <svg class="size-5 text-neutral-900 dark:text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .7a11.3 11.3 0 0 0-3.6 22c.6.1.8-.3.8-.6v-2.2c-3.1.7-3.8-1.3-3.8-1.3-.5-1.3-1.2-1.6-1.2-1.6-1-.7.1-.7.1-.7 1.1.1 1.7 1.1 1.7 1.1 1 1.7 2.6 1.2 3.2.9.1-.7.4-1.2.7-1.5-2.5-.3-5.1-1.3-5.1-5.6 0-1.2.4-2.1 1.1-2.9-.1-.3-.5-1.4.1-2.8 0 0 .9-.3 3 1.1a10.2 10.2 0 0 1 5.5 0c2.1-1.4 3-1.1 3-1.1.6 1.4.2 2.5.1 2.8.7.8 1.1 1.7 1.1 2.9 0 4.3-2.6 5.3-5.1 5.6.4.3.7 1 .7 1.9v2.8c0 .3.2.7.8.6A11.3 11.3 0 0 0 12 .7Z"/></svg>
        @endif
        <span>{{ $label }}</span>
        @unless ($providerRouteAvailable)<span class="ml-auto text-[10px] font-medium uppercase tracking-wide text-neutral-400">{{ __('ui.navigation.soon') }}</span>@endunless
@if ($providerRouteAvailable)</a>@else</button>@endif
