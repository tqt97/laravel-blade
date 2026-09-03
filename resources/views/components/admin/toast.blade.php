@props(['type' => 'success', 'message' => null, 'timeout' => 5000])

@php $tone = $type === 'error' ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-400/20 dark:bg-red-400/10 dark:text-red-200' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200'; @endphp
<div data-toast data-toast-timeout="{{ $timeout }}" role="status"
    class="{{ $tone }} flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg shadow-neutral-950/10">
    <span class="mt-0.5">{{ $type === 'error' ? '!' : '✓' }}</span>
    <p class="flex-1">{{ $message ?? $slot }}</p><button type="button" data-toast-dismiss
        class="ui-action rounded-md p-0.5 opacity-70 hover:opacity-100" aria-label="{{ __('ui.feedback.close_notification') }}">×</button>
</div>
