<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.movie_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background text-foreground">
    <main class="grid min-h-screen place-items-center px-6 py-16">
        <section class="w-full max-w-xl rounded-3xl border border-border bg-card p-8 text-center shadow-sm sm:p-12" role="alert">
            <p class="text-sm font-bold uppercase tracking-[.25em] text-primary">{{ $code }}</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $title }}</h1>
            <p class="mt-4 leading-7 text-muted-foreground">{{ $message }}</p>
            <a href="{{ route('home') }}" class="mt-8 inline-flex rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary-strong focus:outline-none focus:ring-4 focus:ring-primary/20">
                {{ __('booking.errors.back_home') }}
            </a>
        </section>
    </main>
</body>
</html>
