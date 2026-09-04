@props([
    'title' => __('ui.blank.title'),
    'description' => __('ui.blank.description'),
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'flex min-h-72 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card p-8 text-center']) }}>
    <div class="flex size-14 items-center justify-center rounded-xl bg-muted text-muted-foreground">
        @if (isset($icon))
            {{ $icon }}
        @else
            <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z" />
                <path d="M4 5.5v16M8 8h8M8 12h6" stroke-linecap="round" />
            </svg>
        @endif
    </div>
    <h3 class="mt-5 text-base font-semibold text-card-foreground">
        {{ $title }}
    </h3>
    <p class="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
        {{ $description }}
    </p>
    @if ($actionLabel && $actionHref)
        <x-admin.button :href="$actionHref" class="mt-5">{{ $actionLabel }}</x-admin.button>
    @endif
</div>
