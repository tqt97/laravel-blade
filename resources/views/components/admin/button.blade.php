@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'icon' => null,
    'iconOnly' => false,
])

@php
    $variants = [
        'primary' => 'bg-[#0f172a] text-white shadow-sm shadow-neutral-500/20 hover:bg-[#1e293b] focus:ring-neutral-500/20',
        'secondary' => 'border border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50 focus:ring-neutral-500/10 dark:border-white/10 dark:bg-white/[0.06] dark:text-neutral-200 dark:hover:bg-white/10',
        'danger' => 'bg-rose-600 text-white shadow-sm shadow-rose-500/20 hover:bg-rose-700 focus:ring-rose-500/20 dark:bg-rose-500 dark:hover:bg-rose-600',
        'success' => 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/20 hover:bg-emerald-700 focus:ring-emerald-500/20 dark:bg-emerald-500 dark:hover:bg-emerald-600',
        'ghost' => 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-950 focus:ring-neutral-500/10 dark:text-neutral-300 dark:hover:bg-white/10 dark:hover:text-white',
    ];

    $classes = 'ui-action inline-flex items-center justify-center gap-2 text-sm font-semibold outline-none transition focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 ' . ($iconOnly ? 'size-9 rounded-lg p-0' : 'min-h-10 rounded-lg px-3.5 py-2') . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@php
    $iconMarkup = match ($icon) {
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'save' => '<path d="M5 4h12l2 2v14H5V4Z" /><path d="M8 4v5h8V4M8 20v-6h8v6" />',
        'edit', 'pencil' => '<path d="m4 16-.8 4.8L8 20l10.8-10.8-4-4L4 16Z" /><path d="m13.5 6.5 4 4" />',
        'trash', 'delete' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />',
        'close' => '<path d="m6 6 12 12M18 6 6 18" />',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6" />',
        default => '',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', 'Action') }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconMarkup)<svg class="{{ $iconOnly ? 'size-4.5' : 'size-4' }} shrink-0" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">{!! $iconMarkup !!}</svg>
        @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', 'Action') }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconMarkup)
            <svg class="{{ $iconOnly ? 'size-4.5' : 'size-4' }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconMarkup !!}
            </svg>
        @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </button>
@endif
