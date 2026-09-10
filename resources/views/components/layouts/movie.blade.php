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
            <div class="flex items-center gap-2">
                <nav aria-label="{{ __('cinema.public.main_navigation') }}" class="hidden sm:block">
                    <x-cinema.public-menu />
                </nav>
                <div class="sm:hidden"><x-cinema.public-menu /></div>
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                @auth
                    <x-ui.notification-bell />
                    <x-admin.button href="{{ route('user.dashboard') }}" icon="dashboard" iconOnly
                        title="{{ __('cinema.public.my_account') }}" compact />
                @else
                    <x-admin.button href="{{ route('login') }}" variant="secondary" icon="arrow-right" iconOnly
                        title="{{ __('cinema.public.login') }}" compact />
                @endauth
            </div>
        </div>
    </header>
    <main id="main-content">{{ $slot }}</main>
    @stack('scripts')
    <footer class="mt-16 border-t border-border bg-card">
        <div class="mx-auto max-w-7xl px-5 py-8 text-sm text-muted-foreground sm:px-8">
            {{ config('app.movie_name') }} · {{ __('cinema.public.footer') }}</div>
    </footer>
</body>

</html>
