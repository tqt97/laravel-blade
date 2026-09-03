@props([
    'title' => __('ui.blank.title'),
    'description' => __('ui.blank.description'),
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-200 bg-white p-8 text-center dark:border-white/10 dark:bg-white/[0.03]']) }}>
    <div
        class="flex size-14 items-center justify-center rounded-2xl bg-neutral-50 text-[#0f172a] dark:bg-neutral-400/10 dark:text-[#e2e8f0]">
        @if (isset($icon))
            {{ $icon }}
        @else
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z" />
                <path d="M4 5.5v16M8 8h8M8 12h6" stroke-linecap="round" />
            </svg>
        @endif
    </div>
    <h3 class="mt-5 text-base font-semibold text-neutral-950 dark:text-white">
        {{ $title }}
    </h3>
    <p class="mt-2 max-w-md text-sm leading-6 text-neutral-500 dark:text-neutral-400">
        {{ $description }}
    </p>
    @if ($actionLabel && $actionHref)
        <x-admin.button :href="$actionHref" class="mt-5">{{ $actionLabel }}</x-admin.button>
    @endif
</div>
