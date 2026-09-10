@php
    $movieDescription = \Illuminate\Support\Str::limit(strip_tags((string) ($movie->synopsis ?: __('cinema.public.no_synopsis'))), 155);
    $movieImage = $movie->poster_url;
    $movieSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Movie',
        'name' => $movie->title,
        'description' => $movieDescription,
        'url' => url()->current(),
        'image' => $movieImage,
        'duration' => 'PT' . (int) $movie->duration_minutes . 'M',
        'workPresented' => $movie->title,
    ];
    $screeningSchemas = $movie->screenings->map(function ($screening) use ($movie, $screeningSummaries): array {
        $summary = $screeningSummaries[$screening->id];
        $currency = strtoupper((string) $screening->currency);
        $fractionDigits = \App\Support\Money\Currency::fractionDigits($currency);

        return [
            '@type' => 'ScreeningEvent',
            'name' => $movie->title,
            'url' => route('cinema.screenings.show', [$movie, $screening]),
            'startDate' => $screening->starts_at->toIso8601String(),
            'endDate' => $screening->ends_at->toIso8601String(),
            'location' => [
                '@type' => 'Place',
                'name' => $screening->room->name,
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => (int) $screening->base_price_minor_units / (10 ** $fractionDigits),
                'priceCurrency' => $currency,
                'availability' => $summary['available'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                'url' => route('cinema.screenings.show', [$movie, $screening]),
            ],
        ];
    })->values()->all();
@endphp
<x-layouts.movie :title="$movie->title" :description="$movieDescription" :image="$movieImage">
    @push('head')
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => [$movieSchema, ...$screeningSchemas]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
    <div class="bg-slate-950 text-white">
      <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
        <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-white/70 hover:text-white">←
            {{ __('cinema.public.movies') }}</a>
        <div class="mt-8 grid items-center gap-8 md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr] lg:gap-12">
            <div class="mx-auto w-48 overflow-hidden rounded-2xl shadow-2xl shadow-black/40 md:mx-0 lg:w-64">@if ($movieImage)<img src="{{ $movieImage }}"
            alt="{{ $movie->title }}" width="440" height="660" fetchpriority="high" decoding="async" class="aspect-[2/3] w-full object-cover">@else<div
                    class="flex aspect-[2/3] items-center justify-center rounded-2xl bg-muted text-5xl">🎬</div>@endif
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[.2em] text-primary">
                    {{ __('cinema.public.movie_details') }}</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">{{ $movie->title }}</h1>
                <div class="mt-5 flex flex-wrap items-center gap-3 text-sm text-white/70"><span class="rounded-md border border-white/20 px-2 py-1 font-bold text-white">{{ $movie->rating ?: 'PG' }}</span><span>{{ $movie->duration_minutes }} min</span>@if ($movie->release_date)<span>·</span><time datetime="{{ $movie->release_date->toDateString() }}">{{ $movie->release_date->format('Y') }}</time>@endif</div>
                <p class="mt-6 max-w-xl leading-8 text-white/70">
                    {{ $movie->synopsis ?: __('cinema.public.no_synopsis') }}</p>
                <a href="#showtimes" class="mt-7 inline-flex rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary-strong">{{ __('cinema.public.view_showtimes') }} ↓</a>
            </div>
        </div>
      </div>
    </div>
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
        <section id="showtimes">
            <h2 class="text-2xl font-semibold">{{ __('cinema.public.select_showtime') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($movie->screenings as $screening)@php($summary = $screeningSummaries[$screening->id])<a
                    href="{{ route('cinema.screenings.show', [$movie, $screening]) }}"
                    class="rounded-2xl border border-border bg-card p-5 transition hover:border-primary hover:shadow-md">
                    <p class="font-semibold">
                        <time datetime="{{ $screening->starts_at->toIso8601String() }}">
                            {{ $screening->starts_at->timezone(config('app.timezone'))->format('D, d/m · H:i') }}
                        </time>
                    </p>
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
</x-layouts.movie>
