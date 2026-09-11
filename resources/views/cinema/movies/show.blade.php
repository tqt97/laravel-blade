@php
    $movieDescription = \Illuminate\Support\Str::limit(
        strip_tags((string) ($movie->synopsis ?: __('cinema.public.no_synopsis'))),
        155,
    );
    $movieImage = $movie->poster_url;
    $movieBackdrop = $movie->backdrop_url;
    $screeningsByDate = $movie->screenings->groupBy(
        fn($screening): string => $screening->starts_at->timezone(config('app.timezone'))->toDateString(),
    );
    $movieSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Movie',
        'name' => $movie->title,
        'description' => $movieDescription,
        'url' => url()->current(),
        'image' => $movieImage,
        'genre' => $movie->genre,
        'director' => ['@type' => 'Person', 'name' => $movie->director],
        'actor' => collect($movie->cast ?? [])
            ->map(fn(string $actor): array => ['@type' => 'Person', 'name' => $actor])
            ->all(),
        'duration' => 'PT' . (int) $movie->duration_minutes . 'M',
        'workPresented' => $movie->title,
    ];
    $screeningSchemas = $movie->screenings
        ->map(function ($screening) use ($movie, $screeningSummaries): array {
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
                    'price' => (int) $screening->base_price_minor_units / 10 ** $fractionDigits,
                    'priceCurrency' => $currency,
                    'availability' =>
                        $summary['available'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                    'url' => route('cinema.screenings.show', [$movie, $screening]),
                ],
            ];
        })
        ->values()
        ->all();
@endphp
<x-layouts.movie :title="$movie->title" :description="$movieDescription" :image="$movieImage">
    @push('head')
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => [$movieSchema, ...$screeningSchemas]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
    <div class="relative isolate overflow-hidden bg-slate-950 text-white">
        @if ($movieBackdrop)
            <div class="absolute inset-0 -z-10 bg-cover bg-center opacity-30 blur-sm"
                style="background-image: url('{{ $movieBackdrop }}')"></div>
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950 via-slate-950/95 to-slate-950/70"></div>
        <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
            <a href="{{ route('cinema.movies.index') }}" class="text-sm font-semibold text-white/70 hover:text-white">←
                {{ __('cinema.public.movies') }}</a>
            <div class="mt-8 grid items-center gap-8 md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr] lg:gap-12">
                <div class="mx-auto w-48 overflow-hidden rounded-2xl shadow-2xl shadow-black/40 md:mx-0 lg:w-64">
                    @if ($movieImage)
                        <img src="{{ $movieImage }}" alt="{{ $movie->title }}" width="440" height="660"
                        fetchpriority="high" decoding="async" class="aspect-[2/3] w-full object-cover">@else<div
                            class="flex aspect-[2/3] items-center justify-center rounded-2xl bg-muted text-5xl">🎬</div>
                    @endif
                </div>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[.2em] text-primary">
                        {{ __('cinema.public.movie_details') }}</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">{{ $movie->title }}</h1>
                    <div class="mt-5 flex flex-wrap items-center gap-3 text-sm text-white/70"><span
                            class="rounded-md border border-white/20 px-2 py-1 font-bold text-white">{{ $movie->rating ?: 'PG' }}</span><span>{{ $movie->duration_minutes }}
                            min</span>
                        @if ($movie->release_date)
                            <span>·</span><time
                                datetime="{{ $movie->release_date->toDateString() }}">{{ $movie->release_date->format('Y') }}</time>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-white/75">
                        @foreach (array_filter([$movie->genre, $movie->language, $movie->format]) as $tag)
                            <span
                                class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <p class="mt-6 max-w-xl leading-8 text-white/70">
                        {{ $movie->synopsis ?: __('cinema.public.no_synopsis') }}</p>
                    @if ($movie->director || filled($movie->cast))
                        <p class="mt-5 text-sm text-white/60">
                            @if ($movie->director)
                                <span class="font-semibold text-white/85">{{ __('cinema.public.director') }}:</span>
                                {{ $movie->director }}
                                @endif @if (filled($movie->cast))
                                    <span
                                        class="ml-3 font-semibold text-white/85">{{ __('cinema.public.cast') }}:</span>
                                    {{ implode(', ', $movie->cast) }}
                                @endif
                        </p>
                    @endif
                    <a href="#showtimes"
                        class="mt-7 inline-flex rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition hover:bg-primary-strong">{{ __('cinema.public.view_showtimes') }}
                        ↓</a>
                </div>
            </div>
        </div>
    </div>
    <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
        <section id="showtimes">
            <div class="flex flex-col justify-between gap-3 border-b border-border pb-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[.18em] text-primary">
                        {{ __('cinema.public.showtimes') }}</p>
                    <h2 class="mt-1 text-3xl font-semibold tracking-tight">{{ __('cinema.public.select_showtime') }}
                    </h2>
                </div>
                <p class="max-w-xs text-sm leading-6 text-muted-foreground sm:text-right">
                    {{ __('cinema.public.hero_description') }}</p>
            </div>
            @forelse ($screeningsByDate as $date => $screenings)
                <div class="mt-8">
                    <div class="mb-4 flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="17" rx="2" />
                                <path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="font-bold text-foreground">
                                <time
                                    datetime="{{ $date }}">{{ $screenings->first()->starts_at->timezone(config('app.timezone'))->isoFormat('dddd, DD/MM') }}</time>
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">{{ $screenings->count() }}
                                {{ __('cinema.public.showtimes') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($screenings as $screening)
                            @php
                                $summary = $screeningSummaries[$screening->id];
                                $hasSeats = $summary['available'] > 0;
                            @endphp
                            <a href="{{ route('cinema.screenings.show', [$movie, $screening]) }}"
                                aria-label="{{ $screening->starts_at->timezone(config('app.timezone'))->format('d/m H:i') }} · {{ $screening->room->name }} · {{ __('cinema.public.choose_seats') }}"
                                class="group relative overflow-hidden rounded-2xl border border-border bg-card p-5 transition duration-300 hover:border-primary/50 hover:shadow-lg {{ $hasSeats ? '' : 'opacity-70' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <time datetime="{{ $screening->starts_at->toIso8601String() }}"
                                        class="text-2xl font-bold tracking-tight text-foreground">
                                        {{ $screening->starts_at->timezone(config('app.timezone'))->format('H:i') }}
                                    </time>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $hasSeats ? 'bg-success-soft text-success-foreground' : 'bg-destructive/10 text-destructive' }}">
                                        {{ $hasSeats ? __('cinema.public.seats_available', $summary) : __('cinema.seats.unavailable') }}
                                    </span>
                                </div>
                                <div class="mt-5 flex items-center gap-2 text-sm text-muted-foreground">
                                    <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                        stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 18v-1a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v1M8 10a4 4 0 1 0 8 0M3 21h18" />
                                    </svg>
                                    <span class="truncate">{{ $screening->room->name }}</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-3 border-t border-border pt-4">
                                    <span class="text-sm font-semibold text-foreground">
                                        {{ \App\Support\Money\Money::fromMinorUnits((int) $screening->base_price_minor_units, strtoupper((string) $screening->currency))->format() }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-lg bg-primary-soft px-3 py-2 text-sm font-bold text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                        {{ __('cinema.public.choose_seats') }}
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            aria-hidden="true">
                                            <path d="M5 12h14m-6-6 6 6-6 6" />
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-muted-foreground">{{ __('cinema.screenings.empty') }}</p>
            @endforelse
        </section>
    </div>
</x-layouts.movie>
