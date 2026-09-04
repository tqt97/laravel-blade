@props(['title', 'description' => null])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-border bg-card']) }}>
    <div class="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="font-semibold text-card-foreground">{{ $title }}</h3>
            @if ($description)
            <p class="mt-1 text-sm text-muted-foreground">{{ $description }}</p>@endif
        </div>
        @if (isset($actions))
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif
    </div>
    <div class="overflow-x-auto">{{ $slot }}</div>
</section>
