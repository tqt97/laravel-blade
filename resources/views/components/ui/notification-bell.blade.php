<div class="relative" data-notification-bell data-notifications-url="{{ route('user.notifications.index') }}" data-read-all-url="{{ route('user.notifications.read-all') }}" data-read-url-template="{{ route('user.notifications.read', ['notification' => '__ID__']) }}" data-empty-label="{{ __('booking.notifications.empty') }}">
    <button type="button" class="relative grid size-10 place-items-center rounded-xl border border-border text-muted-foreground transition hover:border-primary hover:text-foreground" data-notification-toggle aria-expanded="false" aria-controls="notification-panel" aria-label="{{ __('booking.notifications.title') }}">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
        <span class="absolute -right-1 -top-1 hidden min-w-5 rounded-full bg-primary px-1 text-center text-[10px] font-bold leading-5 text-primary-foreground" data-notification-count></span>
    </button>
    <div id="notification-panel" class="absolute right-0 top-12 z-50 hidden w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-border bg-card shadow-xl" data-notification-panel>
        <div class="flex items-center justify-between border-b border-border px-4 py-3"><h2 class="text-sm font-semibold">{{ __('booking.notifications.title') }}</h2><button type="button" class="text-xs font-semibold text-primary hover:underline" data-notification-read-all>{{ __('booking.notifications.mark_all_read') }}</button></div>
        <div class="max-h-96 divide-y divide-border overflow-y-auto" data-notification-list><p class="p-5 text-sm text-muted-foreground">{{ __('booking.notifications.loading') }}</p></div>
    </div>
</div>
