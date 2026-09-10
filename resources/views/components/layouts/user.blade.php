@props(['title' => __('booking.nav.dashboard')])

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
            if (theme === 'dark' || (!theme && prefersDark)) document.documentElement.classList.add('dark');
        })();
    </script>
    <title>{{ $title }} · {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-full bg-background font-sans text-foreground antialiased">
    <div class="user-shell min-h-screen lg:flex" data-user-shell data-mobile-sidebar-open="false">
        <div data-user-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-foreground/50 backdrop-blur-sm lg:hidden"></div>
        <aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-border bg-card transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-border px-5">
                <x-ui.brand-mark />
                <button type="button" data-user-sidebar-close class="flex size-9 items-center justify-center rounded-xl text-muted-foreground transition hover:bg-accent hover:text-foreground lg:hidden" aria-label="{{ __('ui.navigation.close_sidebar') }}">
                    <x-ui.icon name="close" class="size-5" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-5" aria-label="{{ __('ui.navigation.primary') }}">
                <div data-sidebar-group class="mb-6">
                    <p class="px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">{{ __('booking.nav.group') }}</p>
                    <div class="mt-2 space-y-1">
                        <x-admin.nav-item :label="__('booking.nav.dashboard')" href="{{ route('user.dashboard') }}" :active="request()->routeIs('user.dashboard')">
                            <x-slot:icon><x-ui.icon name="dashboard" class="size-5" /></x-slot:icon>
                        </x-admin.nav-item>
                        <x-admin.nav-item :label="__('cinema.public.movies')" href="{{ route('cinema.movies.index') }}" :active="request()->routeIs('cinema.*')">
                            <x-slot:icon><x-ui.icon name="movies" class="size-5" /></x-slot:icon>
                        </x-admin.nav-item>
                        <x-admin.nav-item :label="__('booking.nav.bookings')" href="{{ route('user.bookings.index') }}" :active="request()->routeIs('user.bookings.*')">
                            <x-slot:icon><x-ui.icon name="bookings" class="size-5" /></x-slot:icon>
                        </x-admin.nav-item>
                    </div>
                </div>
            </nav>
        </aside>

        <div class="relative min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-border bg-background/95 px-5 backdrop-blur sm:px-8">
                <button type="button" data-user-sidebar-toggle class="flex size-10 items-center justify-center rounded-xl border border-border text-muted-foreground transition hover:border-primary hover:text-foreground lg:hidden" aria-label="{{ __('ui.navigation.open_sidebar') }}" aria-expanded="false">
                    <x-ui.icon name="menu" class="size-5" />
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <x-ui.notification-bell />
                    <x-ui.language-switcher />
                    <x-ui.theme-toggle />
                    <span class="hidden text-right sm:block">
                        <span class="block text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ __('booking.nav.account') }}</span>
                    </span>
                    <div class="hidden size-10 items-center justify-center rounded-full bg-muted text-sm font-bold text-muted-foreground sm:flex">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground"
                            aria-label="{{ __('ui.app.logout') }}" title="{{ __('ui.app.logout') }}">
                            <x-ui.icon name="logout" class="size-5" />
                        </button>
                    </form>
                </div>
            </header>
            <main class="p-5 sm:p-8">{{ $slot }}</main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>
