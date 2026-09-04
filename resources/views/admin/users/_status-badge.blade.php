@props(['label', 'variant' => 'muted'])

@php
    $variants = [
        'primary' => 'bg-primary-soft text-accent-foreground',
        'success' => 'bg-success-soft text-success-foreground',
        'danger' => 'bg-destructive/10 text-destructive',
        'warning' => 'bg-warning-soft text-warning-foreground',
        'muted' => 'bg-muted text-muted-foreground',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.($variants[$variant] ?? $variants['muted'])]) }}>
    {{ $label }}
</span>
