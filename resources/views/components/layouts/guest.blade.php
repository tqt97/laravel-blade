@props(['title' => config('app.name', 'Laravel'), 'wide' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-neutral-50">

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
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="min-h-full bg-neutral-50 font-sans text-neutral-900 antialiased transition-colors duration-300 dark:bg-neutral-950 dark:text-neutral-100">
    <div class="relative flex min-h-screen flex-col overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,rgba(15,23,42,0.06),transparent_38%),linear-gradient(to_bottom,transparent,rgba(226,232,240,0.45))] dark:bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.08),transparent_38%),linear-gradient(to_bottom,transparent,rgba(15,23,42,0.65))]">
        </div>
        <div
            class="pointer-events-none absolute inset-0 -z-10 opacity-40 bg-[linear-gradient(to_right,rgba(148,163,184,0.12)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.12)_1px,transparent_1px)] bg-size-[48px_48px] dark:opacity-20">
        </div>

        <header class="flex items-center justify-between px-5 py-6 sm:px-8 lg:px-12">
            <x-ui.brand-mark />
            <div class="flex items-center gap-2">
                <x-ui.language-switcher />
                <x-ui.theme-toggle />
            </div>
        </header>

        <main class="flex flex-1 items-center justify-center px-5 pb-12 pt-4 sm:px-8">
            <div class="w-full {{ $wide ? 'max-w-2xl' : 'max-w-md' }}">
                <div
                    class="rounded-3xl border border-neutral-200/80 bg-white/90 p-6 shadow-xl shadow-neutral-900/5 backdrop-blur-xl dark:border-white/10 dark:bg-neutral-900/85 dark:shadow-black/20 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-7 text-center text-xs text-neutral-400">© {{ now()->year }}
                    {{ config('app.name', 'Laravel') }} · {{ __('ui.guest.footer') }}</p>
            </div>
        </main>
    </div>
</body>

</html>
