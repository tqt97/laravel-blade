<x-layouts.movie :title="__('cinema.public.movies')" :description="__('cinema.public.hero_description')">
    <section class="relative isolate overflow-hidden bg-slate-950 text-white">
        <div
            class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_80%_20%,rgb(37_99_235_/_0.28),transparent_32%),radial-gradient(circle_at_10%_100%,rgb(124_58_237_/_0.25),transparent_35%)]">
        </div>
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
            <p class="text-sm font-bold uppercase tracking-[.25em] text-white/60">{{ __('cinema.public.eyebrow') }}</p>
            <h1 class="mt-4 max-w-2xl text-4xl font-semibold tracking-tight sm:text-6xl">
                {{ __('cinema.public.hero_title') }}
            </h1>
            <p class="mt-5 max-w-xl text-white/70">{{ __('cinema.public.hero_description') }}</p>
        </div>
    </section>
    <section id="showtimes" class="mx-auto max-w-7xl space-y-8 px-5 py-12 sm:px-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('cinema.public.now_showing') }}</p>
                <h2 class="mt-1 text-3xl font-semibold tracking-tight">{{ __('cinema.public.choose_movie') }}</h2>
            </div>
            <p class="text-sm text-muted-foreground">{{ $movies->total() }} {{ __('cinema.public.movies') }}</p>
        </div>
        <form method="GET" action="{{ route('cinema.movies.index') }}"
            class="flex flex-col gap-2 sm:flex-row sm:items-end" role="search">
            <div class="min-w-0 flex-1">
                <label for="movie-search" class="text-sm font-semibold">
                    {{ __('cinema.public.search_label') }}
                </label>
                <input id="movie-search" name="search" type="search" value="{{ request('search') }}"
                    placeholder="{{ __('cinema.public.search_placeholder') }}" autocomplete="off"
                    class="mt-2 block w-full rounded-xl border border-border bg-card px-4 py-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-4 focus:ring-primary/15">
            </div>
            <x-admin.button type="submit" icon="search"
                class="sm:shrink-0">{{ __('cinema.public.search_submit') }}</x-admin.button>
            @if (request()->filled('search'))
                <x-admin.button href="{{ route('cinema.movies.index') }}" variant="ghost" class="sm:shrink-0">
                    {{ __('cinema.public.search_clear') }}
                </x-admin.button>
            @endif
        </form>
        @if ($movies->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-10 text-center text-muted-foreground">
                {{ request()->filled('search') ? __('cinema.public.search_empty') : __('cinema.screenings.empty') }}
        </div>@else<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($movies as $movie)
                    <article
                        class="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition duration-300 hover:border-primary/40 hover:shadow-xl">
                        <a href="{{ route('cinema.movies.show', $movie) }}"
                            class="relative block aspect-video overflow-hidden bg-muted">
                            @if ($movie->backdrop_url ?: $movie->poster_url)
                                <img src="{{ $movie->backdrop_url ?: $movie->poster_url }}" alt="{{ $movie->title }}"
                                    width="640" height="360" loading="lazy" decoding="async"
                                    class="size-full object-cover transition duration-500 group-hover:scale-105">
                                <span
                                    class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></span>
                            @else
                                <div class="flex size-full items-center justify-center bg-muted text-5xl">🎬</div>
                            @endif
                            <span
                                class="absolute left-3 top-3 rounded-md bg-slate-950/80 px-2 py-1 text-xs font-bold text-white backdrop-blur">{{ $movie->rating ?: 'PG' }}</span>
                            <span
                                class="absolute bottom-3 left-3 text-xs font-semibold text-white/85">{{ $movie->duration_minutes }}
                                min</span>
                        </a>
                        <div class="flex min-h-48 flex-col gap-3 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="line-clamp-2 text-lg font-semibold leading-snug">{{ $movie->title }}</h3>
                                <span
                                    class="shrink-0 text-xs font-semibold text-muted-foreground">{{ __('cinema.public.now_showing') }}</span>
                            </div>
                            @if ($movie->screenings->first())
                                <p class="text-sm font-semibold text-primary">
                                    {{ __('cinema.public.next_showtime') }}:
                                    {{ $movie->screenings->first()->starts_at->timezone(config('app.timezone'))->format('d/m · H:i') }}
                                </p>
                            @endif
                            <div class="mt-auto pt-2">
                                <x-admin.button href="{{ route('cinema.movies.show', $movie) }}" icon="arrow-right"
                                    class="w-full">{{ __('cinema.public.view_showtimes') }}</x-admin.button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $movies->withQueryString()->links() }}
        @endif
    </section>
</x-layouts.movie>
