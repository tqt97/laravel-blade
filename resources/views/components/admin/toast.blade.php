@props(['type' => 'success', 'message' => null, 'timeout' => 5000])

@php $tone = $type === 'error' ? 'border-destructive/25 bg-destructive/10 text-destructive' : 'border-success/30 bg-success/10 text-success-foreground'; @endphp
<div data-toast data-toast-timeout="{{ $timeout }}" role="status"
    class="{{ $tone }} flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-lg shadow-foreground/10">
    <span class="mt-0.5">{{ $type === 'error' ? '!' : '✓' }}</span>
    <p class="flex-1">{{ $message ?? $slot }}</p><button type="button" data-toast-dismiss
        class="ui-action rounded-md p-0.5 opacity-70 hover:opacity-100"
        aria-label="{{ __('ui.feedback.close_notification') }}">
        ×
    </button>
</div>
