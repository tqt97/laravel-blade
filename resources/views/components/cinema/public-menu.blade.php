@if (count($entries) > 0)
    <details class="group relative">
        <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold transition hover:bg-accent hover:text-primary">
            {{ __('cinema.public.menu_title') }}
            <svg class="size-4 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </summary>
        <div class="absolute right-0 top-full z-40 mt-2 w-72 rounded-2xl border border-border bg-card p-2 shadow-xl shadow-foreground/10">
            @foreach ($entries as $item)
                <a href="{{ $item['href'] }}" class="block rounded-xl px-3 py-3 transition hover:bg-accent">
                    <span class="block text-sm font-semibold">{{ $item['label'] }}</span>
                    @if ($item['description'])<span class="mt-1 block text-xs text-muted-foreground">{{ $item['description'] }}</span>@endif
                </a>
            @endforeach
        </div>
    </details>
@endif
