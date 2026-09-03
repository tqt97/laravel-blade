@props(['label', 'href' => '#', 'active' => false, 'disabled' => false, 'badge' => null])

@php
    $itemClasses = $active
        ? 'bg-neutral-950 text-white shadow-sm dark:bg-white dark:text-neutral-950'
        : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-white/[0.06] dark:hover:text-white';
@endphp

@if ($disabled)
    <span data-sidebar-nav-item data-sidebar-tooltip="{{ $label }}" title="{{ $label }} · {{ __('ui.navigation.soon') }}"
        class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-neutral-400 opacity-60 dark:text-neutral-500">
@else
        <a data-sidebar-nav-item data-sidebar-tooltip="{{ $label }}" href="{{ $href }}" @if ($active) aria-current="page"
        @endif class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ $itemClasses }}">
    @endif
        <span class="flex size-5 shrink-0 items-center justify-center">{{ $icon }}</span>
        <span data-sidebar-label class="min-w-0 flex-1 truncate">{{ $label }}</span>

        @if ($badge)
            <span data-sidebar-badge
                class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-500 dark:bg-white/10 dark:text-neutral-400">{{ $badge }}
            </span>
        @endif
        @if ($disabled)
            </span>
        @else
    </a>
@endif
