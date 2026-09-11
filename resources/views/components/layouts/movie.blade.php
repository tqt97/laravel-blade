@props([
    'title' => config('app.movie_name'),
    'description' => null,
    'image' => null,
    'canonical' => url()->current(),
    'robots' => null,
])

@php
    $robots = $robots ?? (request()->routeIs('user.*', 'admin.*') ? 'noindex,nofollow,noarchive' : 'index,follow');
    $description = $description ?: __('cinema.public.hero_description');
    $image = $image ?: null;
    $userInitial = auth()->check() ? mb_strtoupper(mb_substr(trim((string) auth()->user()->name), 0, 1)) : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }} · {{ config('app.movie_name') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} · {{ config('app.movie_name') }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($image)
        <meta name="twitter:image" content="{{ $image }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>{{ $title }} · {{ config('app.movie_name') }}</title>
    @stack('head')
</head>

<body class="min-h-full bg-background font-sans text-foreground antialiased">
    <a href="#main-content"
        class="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        {{ __('cinema.public.skip_to_content') }}
    </a>
    <header class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
            <x-ui.movie-brand-mark />
            <form method="GET" action="{{ route('cinema.movies.index') }}" class="relative hidden min-w-0 flex-1 md:flex md:max-w-md"
                role="search">
                <label for="global-movie-search" class="sr-only">{{ __('cinema.public.search_global_label') }}</label>
                <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input id="global-movie-search" name="search" type="search" value="{{ request('search') }}"
                    placeholder="{{ __('cinema.public.search_global_placeholder') }}" autocomplete="off"
                    class="h-10 w-full rounded-xl border border-border bg-card pl-10 pr-4 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/15">
            </form>
            <div class="flex items-center gap-2">
                <nav aria-label="{{ __('cinema.public.main_navigation') }}" class="hidden sm:block">
                    <x-cinema.public-menu />
                </nav>
                <div class="sm:hidden"><x-cinema.public-menu /></div>
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                @auth
                    <x-ui.notification-bell />
                    <details class="group relative" data-user-menu>
                        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-xl p-1 transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                            <span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-extrabold text-primary-foreground shadow-sm"
                                aria-hidden="true">{{ $userInitial }}</span>
                            {{-- <span class="hidden max-w-28 truncate text-left text-xs font-semibold text-foreground lg:block">{{ auth()->user()->name }}</span> --}}
                            <x-ui.icon name="chevron-down" class="hidden size-4 text-muted-foreground transition group-open:rotate-180 lg:block" />
                            <span class="sr-only">{{ __('cinema.public.account_menu') }}</span>
                        </summary>
                        <div class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-2xl border border-border bg-card p-2 shadow-xl shadow-foreground/10">
                            <div class="flex items-center gap-3 border-b border-border px-3 py-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-soft text-sm font-extrabold text-primary">{{ $userInitial }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-foreground">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            <nav class="py-2" aria-label="{{ __('cinema.public.account_menu') }}">
                                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-primary">
                                    <x-ui.icon name="user" class="size-4 text-muted-foreground" />
                                    {{ __('cinema.public.profile') }}
                                </a>
                                <a href="{{ route('user.bookings.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-primary">
                                    <x-ui.icon name="bookings" class="size-4 text-muted-foreground" />
                                    {{ __('booking.nav.bookings') }}
                                </a>
                                @if (auth()->user()->is_admin)
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-primary">
                                        <x-ui.icon name="shield" class="size-4 text-muted-foreground" />
                                        {{ __('cinema.public.admin_portal') }}
                                    </a>
                                @endif
                            </nav>
                            <div class="border-t border-border pt-2">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-destructive transition hover:bg-destructive/10">
                                        <x-ui.icon name="logout" class="size-4" />
                                        {{ __('ui.app.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                @else
                    <x-admin.button href="{{ route('login') }}" variant="secondary" icon="login" iconOnly
                        title="{{ __('cinema.public.login') }}" compact />
                @endauth
            </div>
        </div>
    </header>
    <main id="main-content">{{ $slot }}</main>
    @stack('scripts')
    <footer class="mt-16 border-t border-border bg-card" aria-labelledby="movie-footer-title">
        <div class="mx-auto max-w-7xl px-5 pb-7 pt-12 sm:px-8 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.5fr_repeat(3,minmax(0,1fr))]">
                <div class="max-w-sm">
                    <h2 id="movie-footer-title" class="sr-only">{{ config('app.movie_name') }}</h2>
                    <x-ui.movie-brand-mark class="h-9 w-auto" />
                    <p class="mt-5 text-sm leading-6 text-muted-foreground">
                        {{ __('cinema.public.footer_description') }}
                    </p>
                    <div class="mt-5 inline-flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1.5 text-xs font-semibold text-muted-foreground">
                        <svg class="size-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M3 12h18M12 3c2.2 2.4 3.3 5.4 3.3 9s-1.1 6.6-3.3 9c-2.2-2.4-3.3-5.4-3.3-9S9.8 5.4 12 3Z" />
                        </svg>
                        {{ config('app.timezone') }}
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-foreground">{{ __('cinema.public.footer_explore') }}</h3>
                    <nav class="mt-4 flex flex-col items-start gap-3 text-sm text-muted-foreground" aria-label="{{ __('cinema.public.footer_explore') }}">
                        <a href="{{ route('cinema.movies.index') }}" class="transition hover:text-primary">
                            {{ __('cinema.public.footer_all_movies') }}
                        </a>
                    </nav>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-foreground">{{ __('cinema.public.footer_account') }}</h3>
                    <nav class="mt-4 flex flex-col items-start gap-3 text-sm text-muted-foreground" aria-label="{{ __('cinema.public.footer_account') }}">
                        @auth
                            <a href="{{ route('user.dashboard') }}" class="transition hover:text-primary">
                                {{ __('cinema.public.my_account') }}
                            </a>
                            <a href="{{ route('user.bookings.index') }}" class="transition hover:text-primary">
                                {{ __('booking.nav.bookings') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="transition hover:text-primary">
                                {{ __('cinema.public.login') }}
                            </a>
                        @endauth
                    </nav>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-foreground">{{ __('cinema.public.footer_support') }}</h3>
                    <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                        <p>{{ __('cinema.public.footer_help') }}</p>
                        <a href="mailto:{{ config('mail.from.address') }}" class="inline-flex items-center gap-2 transition hover:text-primary">
                            <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" />
                            </svg>
                            {{ config('mail.from.address') }}
                        </a>
                        <p class="text-xs text-muted-foreground/80">{{ __('cinema.public.footer_hours') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex flex-col gap-3 border-t border-border pt-5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ now()->year }} {{ config('app.movie_name') }}. {{ __('cinema.public.footer_rights') }}</p>
                <p>{{ __('cinema.public.footer') }}</p>
            </div>
        </div>
    </footer>
</body>

</html>
