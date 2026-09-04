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
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-full bg-background font-sans text-foreground antialiased">
    <div class="relative isolate min-h-screen overflow-hidden">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(circle_at_top,oklch(0.70_0.15_250_/_0.16),transparent_65%)]">
        </div>
        <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8 lg:px-12">
            <x-ui.brand-mark />
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
                @auth
                    <x-admin.button
                        href="{{ auth()->user()->is_admin ? route('admin.dashboard') : route('user.dashboard') }}" compact
                        icon="arrow-right">{{ __('ui.dashboard.home') }}</x-admin.button>
                @else
                    @if (Route::has('login'))
                        <x-admin.button href="{{ route('login') }}" variant="secondary"
                            compact>{{ __('ui.auth_pages.login_title') }}</x-admin.button>
                    @endif
                @endauth
            </div>
        </header>
        <main class="mx-auto flex max-w-7xl flex-col gap-16 px-5 pb-16 pt-16 sm:px-8 lg:px-12 lg:pt-24">
            <section class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="max-w-2xl">
                    <p
                        class="mb-5 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary-soft px-3 py-1.5 text-xs font-semibold text-accent-foreground">
                        <span class="size-1.5 rounded-full bg-primary"></span>{{ __('ui.dashboard.online') }}</p>
                    <h1 class="text-4xl font-semibold tracking-tight text-foreground sm:text-6xl">
                        {{ __('ui.dashboard.welcome') }}</h1>
                    <p class="mt-6 max-w-xl text-base leading-8 text-muted-foreground sm:text-lg">
                        {{ __('ui.dashboard.description') }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        @guest
                            @if (Route::has('register'))
                                <x-admin.button href="{{ route('register') }}"
                                    icon="arrow-right">{{ __('ui.auth_pages.sign_up') }}</x-admin.button>
                            @endif
                        @endguest
                        <x-admin.button href="#features"
                            variant="secondary">{{ __('ui.dashboard.continue_learning') }}</x-admin.button>
                    </div>
                </div>
                <div class="rounded-3xl border border-border bg-card p-6 shadow-xl shadow-primary/10 sm:p-8">
                    <div class="flex items-center justify-between border-b border-border pb-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                {{ __('ui.dashboard.overview') }}</p>
                            <h2 class="mt-2 text-xl font-semibold text-card-foreground">Laravel workspace</h2>
                        </div><span
                            class="rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success-foreground">{{ __('ui.dashboard.online') }}</span>
                    </div>
                    <div class="mt-6 space-y-5">
                        <div>
                            <div class="flex items-center justify-between text-sm"><span
                                    class="font-medium text-card-foreground">{{ __('ui.dashboard.completed_lessons') }}</span><span
                                    class="text-muted-foreground">68%</span></div>
                            <div class="mt-3 h-2 rounded-full bg-muted">
                                <div class="h-full w-[68%] rounded-full bg-primary"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-muted p-4">
                                <p class="text-2xl font-semibold text-card-foreground">12</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ __('ui.dashboard.completed_lessons') }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-muted p-4">
                                <p class="text-2xl font-semibold text-card-foreground">03</p>
                                <p class="mt-1 text-xs text-muted-foreground">{{ __('ui.dashboard.active_projects') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section id="features" class="grid gap-4 md:grid-cols-3">
                @foreach ([['title' => __('ui.dashboard.continue_learning'), 'description' => __('ui.dashboard.description')], ['title' => __('ui.dashboard.shortcuts'), 'description' => __('ui.dashboard.coming_soon_description')], ['title' => __('ui.dashboard.recent_activity'), 'description' => __('ui.dashboard.completed_lesson')]] as $feature)
                    <article class="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary-soft text-primary">→
                        </div>
                        <h2 class="mt-5 font-semibold text-card-foreground">{{ $feature['title'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ $feature['description'] }}</p>
                    </article>
                @endforeach
            </section>
        </main>
        <footer class="mx-auto max-w-7xl px-5 pb-8 text-center text-xs text-muted-foreground sm:px-8 lg:px-12">©
            {{ now()->year }} {{ config('app.name', 'Laravel') }} · {{ __('ui.guest.footer') }}</footer>
    </div>
</body>

</html>
