@props(['title' => __('ui.navigation.dashboard'), 'heading' => __('ui.navigation.dashboard')])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (() => {
            const theme = localStorage.getItem('app-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            if (theme === 'dark' || (!theme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <title>{{ $title }} · {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-full bg-background font-sans text-foreground antialiased transition-colors duration-300">
    <div class="admin-shell min-h-screen lg:flex" data-admin-shell data-sidebar-collapsed="false"
        data-mobile-sidebar-open="false">
        <div data-sidebar-mobile-backdrop
            class="admin-sidebar-backdrop fixed inset-0 z-40 hidden bg-foreground/50 backdrop-blur-sm lg:hidden"></div>

        <aside
            class="admin-sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-border bg-card transition-[width,transform] duration-300 lg:sticky lg:top-0 lg:h-screen lg:self-start lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-border px-5">
                <x-ui.brand-mark />
                <button type="button" data-sidebar-mobile-close
                    class="flex size-9 items-center justify-center rounded-xl text-muted-foreground transition hover:bg-accent hover:text-foreground lg:hidden"
                    aria-label="{{ __('ui.navigation.close_sidebar') }}">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <nav class="admin-sidebar-nav flex-1 overflow-y-auto px-3 py-5"
                aria-label="{{ __('ui.navigation.primary') }}">
                <div data-sidebar-group class="mb-6">
                    <button type="button" data-sidebar-group-button aria-expanded="true"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground transition hover:text-foreground">
                        <span data-sidebar-label>{{ __('ui.navigation.workspace') }}</span>
                        <svg data-sidebar-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div data-sidebar-group-content class="mt-2 space-y-1">
                        <x-admin.nav-item :label="__('ui.navigation.dashboard')" href="{{ route('admin.dashboard') }}"
                            :active="request()->routeIs('admin.dashboard')">
                            <x-slot:icon>
                                <svg class="size-5 text-muted-foreground" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect width="7" height="7" x="3" y="3" rx="1" />
                                    <rect width="7" height="7" x="14" y="3" rx="1" />
                                    <rect width="7" height="7" x="3" y="14" rx="1" />
                                    <rect width="7" height="7" x="14" y="14" rx="1" />
                                </svg>
                            </x-slot:icon>
                        </x-admin.nav-item>
                        <x-admin.nav-item :label="__('ui.navigation.lessons')" disabled
                            :badge="__('ui.navigation.soon')">
                            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" aria-hidden="true">
                                    <path
                                        d="M4 19.5V4.5A2.5 2.5 0 0 1 6.5 2H20v17H6.5A2.5 2.5 0 0 0 4 21.5m0-2A2.5 2.5 0 0 1 6.5 17H20" />
                                </svg>
                            </x-slot:icon>
                        </x-admin.nav-item>
                    </div>
                </div>

                <div data-sidebar-group class="mb-6">
                    <button type="button" data-sidebar-group-button aria-expanded="true"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground transition hover:text-foreground">
                        <span data-sidebar-label>{{ __('ui.navigation.management') }}</span>
                        <svg data-sidebar-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div data-sidebar-group-content class="mt-2 space-y-1">
                        <x-admin.nav-item :label="__('ui.navigation.users')" href="{{ route('admin.users.index') }}"
                            :active="request()->routeIs('admin.users.*')">
                            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" aria-hidden="true">
                                    <path
                                        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                            </x-slot:icon>
                        </x-admin.nav-item>
                        <x-admin.nav-item :label="__('ui.navigation.activity')" disabled
                            :badge="__('ui.navigation.soon')">
                            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 3v18h18" />
                                    <path d="m7 15 3-3 3 2 5-6" />
                                </svg>
                            </x-slot:icon>
                        </x-admin.nav-item>
                    </div>
                </div>

                <div data-sidebar-group class="mb-6">
                    <button type="button" data-sidebar-group-button aria-expanded="true"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground transition hover:text-foreground">
                        <span data-sidebar-label>{{ __('booking.nav.group') }}</span>
                        <svg data-sidebar-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div data-sidebar-group-content class="mt-2 space-y-1">
                        <x-admin.nav-item :label="__('booking.nav.resources')" href="{{ route('admin.resources.index') }}" :active="request()->routeIs('admin.resources.*')">
                            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M8 4v16M3 9h5M3 15h5" /></svg></x-slot:icon>
                        </x-admin.nav-item>
                        <x-admin.nav-item :label="__('booking.nav.bookings')" href="{{ route('admin.bookings.index') }}" :active="request()->routeIs('admin.bookings.*')">
                            <x-slot:icon><svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></svg></x-slot:icon>
                        </x-admin.nav-item>
                    </div>
                </div>

                <div data-sidebar-group>
                    <button type="button" data-sidebar-group-button aria-expanded="true"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground transition hover:text-foreground">
                        <span data-sidebar-label>{{ __('ui.navigation.system') }}</span>
                        <svg data-sidebar-chevron class="size-4 transition-transform" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div data-sidebar-group-content class="mt-2 space-y-1">
                        <x-admin.nav-item :label="__('ui.navigation.security')"
                            href="{{ route('admin.settings.security') }}"
                            :active="request()->routeIs('admin.settings.security')">
                            <x-slot:icon>
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
                                    <path
                                        d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.2h-2.6v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.5-1H6v-2.6h.5A1.7 1.7 0 0 0 8 10a1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5v-.2H15v.2a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.2V14h-.2a1.7 1.7 0 0 0-1.5 1Z" />
                                </svg>
                            </x-slot:icon>
                        </x-admin.nav-item>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="relative min-w-0 flex-1">
            <button type="button" data-sidebar-toggle
                data-sidebar-expand-label="{{ __('ui.navigation.expand_sidebar') }}"
                data-sidebar-collapse-label="{{ __('ui.navigation.collapse_sidebar') }}"
                class="fixed top-3 z-50 hidden size-10 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition-[left,color,background-color,border-color,box-shadow] hover:border-primary hover:text-foreground lg:flex"
                aria-label="{{ __('ui.navigation.collapse_sidebar') }}" aria-expanded="true"
                title="{{ __('ui.navigation.collapse_sidebar') }}">
                <svg data-sidebar-icon-expanded class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" aria-hidden="true">
                    <path d="m15 18-6-6 6-6" />
                </svg>
                <svg data-sidebar-icon-collapsed class="hidden size-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m9 18 6-6-6-6" />
                </svg>
            </button>
            <header
                class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-border bg-background/95 px-5 backdrop-blur sm:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" data-sidebar-mobile-toggle
                        class="flex size-10 items-center justify-center rounded-xl border border-border text-muted-foreground transition hover:border-primary hover:text-foreground lg:hidden"
                        aria-label="{{ __('ui.navigation.open_sidebar') }}" aria-expanded="false">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-3">
                        @if (isset($breadcrumbs))
                            <div class="hidden shrink-0 border-border pl-3 md:block">
                                {{ $breadcrumbs }}
                            </div>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2"><x-ui.language-switcher /><x-ui.theme-toggle /><span
                        class="hidden text-right sm:block"><span
                            class="block text-sm font-semibold">{{ auth()->user()->name }}</span><span
                            class="block text-xs text-muted-foreground">{{ __('ui.app.active') }}</span></span>
                    <div
                        class="hidden size-10 items-center justify-center rounded-full bg-muted text-sm font-bold text-muted-foreground sm:flex">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"
                            class="rounded-xl p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                            aria-label="{{ __('ui.app.logout') }}" title="{{ __('ui.app.logout') }}"><svg class="size-5"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path d="M10 17l5-5-5-5M15 12H3" />
                                <path d="M21 19V5a2 2 0 0 0-2-2h-6" />
                            </svg></button></form>
                </div>
            </header>
            <main class="p-5 sm:p-8">{{ $slot }}</main>
        </div>
    </div>
</body>

</html>
