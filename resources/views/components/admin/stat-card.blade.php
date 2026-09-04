@props(['label', 'value', 'delta' => null, 'positive' => true])

<div class="rounded-xl border border-border bg-card p-5 transition hover:border-primary/30">
    <div class="flex items-start justify-between gap-4">
        <p class="text-sm font-medium text-muted-foreground">{{ $label }}</p>
        <div class="flex size-10 items-center justify-center rounded-xl bg-muted text-muted-foreground">
            {{ $icon }}
        </div>
    </div>
    <div class="mt-5 flex items-end justify-between gap-3">
        <p class="text-3xl font-semibold tracking-tight">{{ $value }}</p>
        @if ($delta)
            <span
                class="mb-1 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ $positive ? 'bg-success-soft text-success-foreground' : 'bg-destructive/10 text-destructive' }}">{{ $positive ? '↗' : '↘' }}
                {{ $delta }}</span>
        @endif
    </div>
</div>
