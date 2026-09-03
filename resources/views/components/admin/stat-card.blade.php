@props(['label', 'value', 'delta' => null, 'positive' => true])

<div
    class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-neutral-900 dark:hover:border-white/20">
    <div class="flex items-start justify-between gap-4">
        <p class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ $label }}</p>
        <div
            class="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600 dark:bg-white/[0.06] dark:text-neutral-300">
            {{ $icon }}</div>
    </div>
    <div class="mt-5 flex items-end justify-between gap-3">
        <p class="text-3xl font-semibold tracking-tight">{{ $value }}</p>
        @if ($delta)
            <span
                class="mb-1 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ $positive ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300' }}">{{ $positive ? '↗' : '↘' }}
                {{ $delta }}</span>
        @endif
    </div>
</div>
