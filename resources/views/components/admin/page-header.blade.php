@props(['title', 'description' => null])

<div class="space-y-4 border-b border-neutral-200 pb-6 dark:border-white/15">
    @if (isset($breadcrumbs))
        {{ $breadcrumbs }}
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-neutral-950 dark:text-white">
                {{ $title }}
            </h2>
            @if ($description)
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-neutral-500 dark:text-neutral-400">{{ $description }}</p>
            @endif
        </div>
        @if (isset($actions))
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endif
    </div>
</div>
