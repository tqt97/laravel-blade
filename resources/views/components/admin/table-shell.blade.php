@props(['title', 'description' => null])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-sm shadow-neutral-200/40 dark:border-white/10 dark:bg-white/[0.03] dark:shadow-none']) }}>
    <div
        class="flex flex-col gap-4 border-b border-neutral-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
        <div>
            <h3 class="font-semibold text-neutral-950 dark:text-white">{{ $title }}</h3>
            @if ($description)
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ $description }}</p>@endif
        </div>
        @if (isset($actions))
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>@endif
    </div>
    <div class="overflow-x-auto">{{ $slot }}</div>
</section>
