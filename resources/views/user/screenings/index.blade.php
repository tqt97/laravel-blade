<x-layouts.user :title="__('cinema.screenings.title')">
    <div class="mx-auto max-w-6xl space-y-8">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ __('cinema.screenings.title') }}</h1>
            <p class="mt-2 text-sm text-muted-foreground">{{ __('cinema.screenings.description') }}</p>
        </div>
        @if ($screenings->isEmpty())
            <div class="rounded-2xl border border-border bg-card p-10 text-center text-sm text-muted-foreground">
                {{ __('cinema.screenings.empty') }}
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($screenings as $screening)
                    <article class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        @if ($screening->movie?->poster_path)
                            <img src="{{ asset('storage/' . $screening->movie->poster_path) }}"
                                alt="{{ $screening->movie->title }}" width="440" height="660" loading="lazy"
                                decoding="async" class="aspect-[2/3] w-full object-cover">
                        @else
                            <div class="flex aspect-[2/3] items-center justify-center bg-muted text-4xl">🎬</div>
                        @endif
                        <div class="space-y-3 p-5">
                            <h2 class="text-lg font-semibold">{{ $screening->movie?->title }}</h2>
                            <p class="text-sm text-muted-foreground">{{ $screening->room?->name }} ·
                                {{ $screening->starts_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                            <x-admin.button :href="route('user.screenings.show', $screening)"
                                icon="eye">{{ __('cinema.screenings.choose_seats') }}</x-admin.button>
                        </div>
                    </article>
                @endforeach
            </div>
            {{ $screenings->links() }}
        @endif
    </div>
</x-layouts.user>
