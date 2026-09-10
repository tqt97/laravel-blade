const updateCount = (root, unreadCount, { shake = true } = {}) => {
    const count = root.querySelector('[data-notification-count]');
    const unread = Number(unreadCount ?? 0);
    const previousUnread = Number(root.dataset.unreadCount ?? 0);
    count.textContent = unread > 99 ? '99+' : String(unread);
    count.classList.toggle('hidden', unread === 0);
    root.dataset.unreadCount = String(unread);
    root.classList.toggle('notification-bell--unread', unread > 0);
    if (shake && unread > previousUnread) {
        root.classList.remove('notification-bell--unread');
        void root.offsetWidth;
        root.classList.add('notification-bell--unread');
    }
};

export const formatNotificationTime = (isoDate, locale = 'en', referenceTime = Date.now()) => {
    const date = new Date(isoDate);
    if (Number.isNaN(date.getTime())) return '';

    const differenceInSeconds = Math.round((date.getTime() - referenceTime) / 1000);
    const absoluteSeconds = Math.abs(differenceInSeconds);
    if (absoluteSeconds < 10) {
        return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(0, 'second');
    }

    const units = absoluteSeconds < 60
        ? ['second', 1]
        : absoluteSeconds < 3600
            ? ['minute', 60]
            : absoluteSeconds < 86400
                ? ['hour', 3600]
                : ['day', 86400];
    const value = Math.round(differenceInSeconds / units[1]);

    return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(value, units[0]);
};

const render = (root, data) => {
    const list = root.querySelector('[data-notification-list]');
    updateCount(root, data.unread_count);
    if (!data.notifications?.length) {
        const emptyState = document.createElement('p');
        emptyState.className = 'flex min-h-28 items-center justify-center px-5 py-8 text-center text-sm text-muted-foreground';
        emptyState.textContent = root.dataset.emptyLabel ?? '';
        list.replaceChildren(emptyState);
        return;
    }
    list.replaceChildren(...data.notifications.map((notification) => {
        const item = document.createElement('div');
        item.dataset.notificationItem = notification.id;
        item.className = 'flex items-start gap-2.5 px-3 py-2.5';
        const indicator = document.createElement('span');
        indicator.className = `mt-1.5 size-2 shrink-0 rounded-full ${notification.read_at ? 'bg-border' : 'bg-primary shadow-[0_0_0_3px_rgb(var(--primary)/.12)]'}`;
        const link = document.createElement('a');
        link.href = notification.url;
        link.dataset.notificationId = notification.id;
        link.className = 'min-w-0 flex-1 text-sm leading-5 transition hover:text-primary';
        const title = document.createElement('strong');
        title.className = 'block truncate font-semibold';
        title.textContent = notification.title;
        const message = document.createElement('span');
        message.className = 'mt-0.5 block line-clamp-2 text-[13px] leading-5 text-muted-foreground';
        message.textContent = notification.message;
        const time = document.createElement('time');
        time.className = 'mt-1 block text-[11px] leading-4 text-muted-foreground/80';
        time.dateTime = notification.created_at ?? '';
        const relativeTime = formatNotificationTime(notification.created_at, root.dataset.locale ?? document.documentElement.lang ?? 'en');
        const absoluteTime = relativeTime && notification.created_at
            ? new Intl.DateTimeFormat(root.dataset.locale ?? document.documentElement.lang ?? 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(notification.created_at))
            : '';
        time.textContent = relativeTime;
        if (absoluteTime) {
            time.title = absoluteTime;
            time.setAttribute('aria-label', absoluteTime);
        }
        link.append(title, message, time);
        const deleteButton = document.createElement('button');
        deleteButton.type = 'button';
        deleteButton.dataset.notificationDelete = notification.id;
        deleteButton.className = 'shrink-0 rounded-lg p-2 text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive';
        deleteButton.setAttribute('aria-label', root.dataset.deleteLabel ?? 'Delete notification');
        deleteButton.title = root.dataset.deleteLabel ?? 'Delete notification';
        deleteButton.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>';
        item.append(indicator, link, deleteButton);
        return item;
    }));
};

export const initNotificationBells = () => {
    document.querySelectorAll('[data-notification-bell]').forEach((root) => {
        if (root.dataset.initialized === 'true') return;
        root.dataset.initialized = 'true';
        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        const list = root.querySelector('[data-notification-list]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const load = async () => {
            const response = await fetch(root.dataset.notificationsUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) render(root, await response.json());
        };
        toggle.addEventListener('click', () => {
            const closed = panel.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', String(!closed));
            if (closed === false) void load();
        });
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target) && !panel.classList.contains('hidden')) {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
        root.querySelector('[data-notification-read-all]')?.addEventListener('click', async () => {
            const response = await fetch(root.dataset.readAllUrl, { method: 'PATCH', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (response.ok) updateCount(root, (await response.json()).unread_count, { shake: false });
            await load();
        });
        root.querySelector('[data-notification-delete-all]')?.addEventListener('click', async () => {
            if (!window.confirm(root.dataset.deleteAllConfirm ?? 'Clear all notifications?')) return;
            const button = root.querySelector('[data-notification-delete-all]');
            button.disabled = true;
            const response = await fetch(root.dataset.deleteAllUrl, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (response.ok) {
                updateCount(root, 0, { shake: false });
                await load();
            }
            button.disabled = false;
        });
        root.addEventListener('click', async (event) => {
            const deleteButton = event.target.closest('[data-notification-delete]');
            if (deleteButton) {
                event.preventDefault();
                const item = deleteButton.closest('[data-notification-item]');
                deleteButton.disabled = true;
                const deleteUrl = root.dataset.deleteUrlTemplate.replace('__ID__', encodeURIComponent(deleteButton.dataset.notificationDelete));
                const response = await fetch(deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                if (response.ok) {
                    item?.remove();
                    updateCount(root, (await response.json()).unread_count, { shake: false });
                    if (!list.querySelector('[data-notification-item]')) {
                        const emptyState = document.createElement('p');
                        emptyState.className = 'flex min-h-28 items-center justify-center px-5 py-8 text-center text-sm text-muted-foreground';
                        emptyState.textContent = root.dataset.emptyLabel ?? '';
                        list.replaceChildren(emptyState);
                    }
                } else {
                    deleteButton.disabled = false;
                }
                return;
            }
            const link = event.target.closest('[data-notification-id]');
            if (!link || link.dataset.read === 'true') return;
            link.dataset.read = 'true';
            const readUrl = root.dataset.readUrlTemplate.replace('__ID__', encodeURIComponent(link.dataset.notificationId));
            const response = await fetch(readUrl, { method: 'PATCH', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (response.ok) updateCount(root, (await response.json()).unread_count, { shake: false });
        });
        void load();
        window.setInterval(load, 15000);
    });
};
