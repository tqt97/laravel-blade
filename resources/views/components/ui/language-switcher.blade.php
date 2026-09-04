<div class="relative" data-language-menu data-locale="{{ app()->getLocale() }}">
    <button type="button" data-language-trigger
        class="ui-action inline-flex h-10 items-center gap-2 rounded-lg border border-border bg-card px-3 text-sm font-semibold text-foreground outline-none transition hover:border-primary focus:ring-4 focus:ring-primary/15"
        aria-haspopup="menu" aria-expanded="false">
        <svg class="size-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3c2.2 2.4 3.3 5.4 3.3 9s-1.1 6.6-3.3 9c-2.2-2.4-3.3-6.6-3.3-9S9.8 5.4 12 3Z" />
        </svg>
        <span data-language-label>{{ app()->getLocale() === 'vi' ? 'Tiếng Việt' : 'English' }}</span>
        <svg class="size-4 text-muted-foreground transition-transform" data-language-chevron viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
        </svg>
    </button>
    <div data-language-options hidden
        class="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-44 origin-top-right rounded-lg border border-border bg-popover p-1.5 shadow-lg"
        role="menu" aria-label="{{ __('ui.app.language') }}">
        <button type="button" data-language-option data-locale="vi"
            class="ui-action flex w-full items-center justify-between rounded-md px-3 py-2.5 text-left text-sm font-medium text-popover-foreground transition hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:outline-none"
            role="menuitem">Tiếng Việt <span data-language-check class="hidden text-success">✓</span></button>
        <button type="button" data-language-option data-locale="en"
            class="ui-action flex w-full items-center justify-between rounded-md px-3 py-2.5 text-left text-sm font-medium text-popover-foreground transition hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:outline-none"
            role="menuitem">English <span data-language-check class="text-success">✓</span></button>
    </div>
</div>
