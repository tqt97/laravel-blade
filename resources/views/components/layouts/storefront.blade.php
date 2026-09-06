@props(['title' => config('app.name', 'Cinema')])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-background">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>{{ $title }} · {{ config('app.name', 'Cinema') }}</title>
</head>

<body class="min-h-full bg-background font-sans text-foreground antialiased">
    <header class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
            <x-ui.brand-mark />
            <x-cinema.public-menu />
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                @auth
                    <x-admin.button href="{{ route('user.dashboard') }}" icon="eye" iconOnly
                        title="{{ __('cinema.public.my_account') }}" compact />
                @else
                    <x-admin.button href="{{ route('login') }}" variant="secondary" icon="arrow-right" iconOnly
                        title="{{ __('cinema.public.login') }}" compact />
                @endauth
            </div>
        </div>
    </header>
    <main>{{ $slot }}</main>
    <footer class="mt-16 border-t border-border bg-card">
        <div class="mx-auto max-w-7xl px-5 py-8 text-sm text-muted-foreground sm:px-8">
            {{ config('app.name', 'Cinema') }} · {{ __('cinema.public.footer') }}</div>
    </footer>
</body>

</html>
