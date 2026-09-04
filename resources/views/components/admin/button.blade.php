@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'icon' => null,
    'iconOnly' => false,
    'compact' => false,
])

@php
    $variants = [
        'primary' => 'bg-primary text-primary-foreground shadow-sm hover:bg-primary-strong',
        'secondary' => 'border border-border bg-secondary text-secondary-foreground hover:bg-muted',
        'danger' => 'bg-destructive text-destructive-foreground shadow-sm hover:brightness-95',
        'success' => 'bg-success text-success-foreground shadow-sm hover:brightness-95',
        'ghost' => 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
    ];

    $classes = 'ui-action inline-flex items-center justify-center gap-2 font-semibold outline-none transition focus:ring-4 disabled:cursor-not-allowed disabled:opacity-50 ' . ($iconOnly ? 'size-9 rounded-lg p-0 text-sm' : ($compact ? 'min-h-9 rounded-lg px-3 text-xs' : 'min-h-10 rounded-lg px-3.5 py-2 text-sm')) . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@php
    $icon = $attributes->get('data-modal-method') === 'PATCH' ? 'restore' : $icon;
    $iconMarkup = match ($icon) {
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'save' => '<path d="M5 4h12l2 2v14H5V4Z" /><path d="M8 4v5h8V4M8 20v-6h8v6" />',
        'edit', 'pencil' => '<path d="m4 16-.8 4.8L8 20l10.8-10.8-4-4L4 16Z" /><path d="m13.5 6.5 4 4" />',
        'trash', 'delete' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />',
        'close' => '<path d="m6 6 12 12M18 6 6 18" />',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6" />',
        'restore' => '<path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v6h6" /><path d="M12 7v5l3 2" />',
        default => '',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', __('ui.actions.confirm')) }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconMarkup)<svg class="{{ $iconOnly ? 'size-4.5' : 'size-4' }} shrink-0" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">{!! $iconMarkup !!}</svg>
        @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', __('ui.actions.confirm')) }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconMarkup)
            <svg class="{{ $iconOnly ? 'size-4.5' : 'size-4' }} shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconMarkup !!}
            </svg>
        @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </button>
@endif
