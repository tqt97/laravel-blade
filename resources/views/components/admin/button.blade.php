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

@php($icon = $attributes->get('data-modal-method') === 'PATCH' ? 'restore' : $icon)

@if ($href)
    <a href="{{ $href }}" @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', __('ui.actions.confirm')) }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="($iconOnly ? 'size-4.5' : 'size-4') . ' shrink-0'" /> @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) @if ($iconOnly && !$attributes->has('aria-label'))
    aria-label="{{ $attributes->get('title', __('ui.actions.confirm')) }}" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" :class="($iconOnly ? 'size-4.5' : 'size-4') . ' shrink-0'" />
        @endif
        @unless ($iconOnly){{ $slot }}
        @endunless
    </button>
@endif
