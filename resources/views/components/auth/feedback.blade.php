@if (session('status'))
    <div class="mb-6 flex gap-3 rounded-2xl border border-success/30 bg-success/10 p-4 text-sm text-success-foreground"
        role="status">
        <svg class="mt-0.5 size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" aria-hidden="true">
            <path d="m5 12 4 4L19 6" />
        </svg>
        <p>{{ __(session('status')) }}</p>
    </div>
@endif

@if (session('warning'))
    <div class="mb-6 flex gap-3 rounded-2xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning-foreground"
        role="status">
        <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" aria-hidden="true">
            <path d="M12 9v4m0 4h.01M10.3 4.8 2.9 18a2 2 0 0 0 1.7 3h14.8a2 2 0 0 0 1.7-3L13.7 4.8a2 2 0 0 0-3.4 0Z" />
        </svg>
        <p>{{ __(session('warning')) }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-destructive/25 bg-destructive/10 p-4 text-sm text-destructive" role="alert" tabindex="-1" data-error-summary>
        <p class="font-semibold">{{ __('ui.feedback.check_information') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-destructive">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
