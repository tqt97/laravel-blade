<x-layouts.storefront :title="__('cinema.public.movies')">
    <section class="bg-slate-950 text-white">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:py-24">
            <p class="text-sm font-bold uppercase tracking-[.25em] text-white/60">{{ __('cinema.public.eyebrow') }}</p>
            <h1 class="mt-4 max-w-2xl text-4xl font-semibold tracking-tight sm:text-6xl">
                {{ __('cinema.public.hero_title') }}
            </h1>
            <p class="mt-5 max-w-xl text-white/70">{{ __('cinema.public.hero_description') }}</p>
        </div>
    </section>
    <section id="showtimes" class="mx-auto max-w-7xl space-y-8 px-5 py-12 sm:px-8">
        <div>
            <p class="text-sm font-semibold text-primary">{{ __('cinema.public.now_showing') }}</p>
            <h2 class="mt-1 text-3xl font-semibold tracking-tight">{{ __('cinema.public.choose_movie') }}</h2>
        </div>
        @if ($movies->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-10 text-center text-muted-foreground">
                {{ __('cinema.screenings.empty') }}
        </div>@else<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($movies as $movie)
                    <article
                        class="group overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        <a href="{{ route('cinema.movies.show', $movie) }}">
                            @if ($movie->poster_path)
                                <img src="{{ asset('storage/' . $movie->poster_path) }}" alt="{{ $movie->title }}"
                            width="440" height="660" loading="lazy" decoding="async" class="aspect-[2/3] w-full object-cover">@else<div
                                    class="flex aspect-[2/3] items-center justify-center bg-muted text-5xl">🎬</div>
                                @endif
                        </a>
                        <div class="space-y-3 p-5">
                            <h3 class="text-lg font-semibold">{{ $movie->title }}</h3>
                            <p class="text-sm text-muted-foreground">{{ $movie->duration_minutes }} min @if ($movie->rating)
                                · {{ $movie->rating }}
                            @endif
                            </p>
                            <x-admin.button href="{{ route('cinema.movies.show', $movie) }}" icon="arrow-right"
                                class="w-full">{{ __('cinema.public.view_showtimes') }}</x-admin.button>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $movies->links() }}
        @endif
    </section>
</x-layouts.storefront>
