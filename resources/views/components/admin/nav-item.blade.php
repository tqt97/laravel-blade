@props(['label', 'href' => '#', 'active' => false, 'disabled' => false, 'badge' => null])

@php
    $itemClasses = $active
        ? 'bg-primary-soft text-accent-foreground ring-1 ring-inset ring-primary/15'
        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground';
@endphp

@if ($disabled)
    <span data-sidebar-nav-item data-sidebar-tooltip="{{ $label }}" title="{{ $label }} · {{ __('ui.navigation.soon') }}"
        class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-muted-foreground opacity-60">
@else
        <a data-sidebar-nav-item data-sidebar-tooltip="{{ $label }}" href="{{ $href }}" @if ($active) aria-current="page"
        @endif class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $itemClasses }}">
    @endif
        <span class="flex size-5 shrink-0 items-center justify-center">{{ $icon }}</span>
        <span data-sidebar-label class="min-w-0 flex-1 truncate">{{ $label }}</span>

        @if ($badge)
            <span data-sidebar-badge
                class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">{{ $badge }}
            </span>
        @endif
        @if ($disabled)
            </span>
        @else
    </a>
@endif
