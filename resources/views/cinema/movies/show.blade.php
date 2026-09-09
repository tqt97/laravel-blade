<x-layouts.storefront :title="$movie->title">
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8">
        <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-primary hover:underline">←
            {{ __('cinema.public.movies') }}</a>
        <div class="mt-8 grid gap-8 md:grid-cols-[220px_1fr]">
            <div>@if ($movie->poster_path)<img src="{{ asset('storage/' . $movie->poster_path) }}"
            alt="{{ $movie->title }}" width="440" height="660" fetchpriority="high" decoding="async" class="aspect-[2/3] w-full rounded-2xl object-cover">@else<div
                    class="flex aspect-[2/3] items-center justify-center rounded-2xl bg-muted text-5xl">🎬</div>@endif
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[.2em] text-primary">
                    {{ __('cinema.public.movie_details') }}</p>
                <h1 class="mt-2 text-4xl font-semibold">{{ $movie->title }}</h1>
                <p class="mt-4 text-muted-foreground">{{ $movie->duration_minutes }} min @if ($movie->rating) ·
                {{ $movie->rating }} @endif</p>
                <p class="mt-6 leading-7 text-muted-foreground">
                    {{ $movie->synopsis ?: __('cinema.public.no_synopsis') }}</p>
            </div>
        </div>
        <section class="mt-12">
            <h2 class="text-2xl font-semibold">{{ __('cinema.public.select_showtime') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($movie->screenings as $screening)@php($summary = $screeningSummaries[$screening->id])<a
                    href="{{ route('cinema.screenings.show', [$movie, $screening]) }}"
                    class="rounded-2xl border border-border bg-card p-5 transition hover:border-primary hover:shadow-md">
                    <p class="font-semibold">
                        {{ $screening->starts_at->timezone($screening->room->timezone)->format('D, d/m · H:i') }}</p>
                    <p class="mt-2 text-sm text-muted-foreground">{{ $screening->room->name }} ·
                        {{ \App\Support\Money\Money::fromMinorUnits((int) $screening->base_price_minor_units, strtoupper((string) $screening->currency))->format() }}</p>
                    <p
                        class="mt-3 text-sm font-medium {{ $summary['available'] > 0 ? 'text-success-foreground' : 'text-destructive' }}">
                        {{ __('cinema.public.seats_available', $summary) }}</p><span
                        class="mt-4 inline-flex text-sm font-semibold text-primary">{{ __('cinema.public.choose_seats') }}
                        →</span>
                </a>@empty<p class="text-muted-foreground">{{ __('cinema.screenings.empty') }}</p>@endforelse</div>
        </section>
    </div>
</x-layouts.storefront>
