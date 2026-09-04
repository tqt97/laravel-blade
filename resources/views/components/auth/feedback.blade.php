@if (session('status'))
    <div class="mb-6 flex gap-3 rounded-2xl border border-success/30 bg-success/10 p-4 text-sm text-success-foreground"
        role="status">
        <svg class="mt-0.5 size-5 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" aria-hidden="true">
            <path d="m5 12 4 4L19 6" />
        </svg>
        <p>{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-destructive/25 bg-destructive/10 p-4 text-sm text-destructive" role="alert">
        <p class="font-semibold">{{ __('ui.feedback.check_information') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-destructive">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
