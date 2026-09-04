<button type="button" data-theme-toggle data-theme-to-light="{{ __('ui.app.theme_to_light') }}"
    data-theme-to-dark="{{ __('ui.app.theme_to_dark') }}" aria-pressed="false"
    class="ui-action inline-flex size-10 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground transition hover:border-primary hover:text-foreground"
    title="{{ __('ui.app.theme_to_dark') }}" aria-label="{{ __('ui.app.theme_to_dark') }}">
    <svg class="size-5 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
        aria-hidden="true">
        <circle cx="12" cy="12" r="3" />
        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
    </svg>
    <svg class="hidden size-5 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
        aria-hidden="true">
        <path d="M20.8 15.2A8.5 8.5 0 0 1 8.8 3.2 8.5 8.5 0 1 0 20.8 15.2Z" />
    </svg>
    <span class="sr-only">{{ __('ui.app.theme_toggle') }}</span>
</button>
