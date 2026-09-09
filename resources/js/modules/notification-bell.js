const render = (root, data) => {
    const count = root.querySelector('[data-notification-count]');
    const list = root.querySelector('[data-notification-list]');
    const unread = Number(data.unread_count ?? 0);
    count.textContent = unread > 99 ? '99+' : String(unread);
    count.classList.toggle('hidden', unread === 0);
    if (!data.notifications?.length) {
        list.textContent = root.dataset.emptyLabel ?? '';
        return;
    }
    list.replaceChildren(...data.notifications.map((notification) => {
        const link = document.createElement('a');
        link.href = notification.url;
        link.dataset.notificationId = notification.id;
        link.className = `block p-4 text-sm transition hover:bg-muted ${notification.read_at ? '' : 'bg-primary-soft/40'}`;
        const title = document.createElement('strong');
        title.className = 'block font-semibold';
        title.textContent = notification.title;
        const message = document.createElement('span');
        message.className = 'mt-1 block text-muted-foreground';
        message.textContent = notification.message;
        link.append(title, message);
        return link;
    }));
};

export const initNotificationBells = () => {
    document.querySelectorAll('[data-notification-bell]').forEach((root) => {
        if (root.dataset.initialized === 'true') return;
        root.dataset.initialized = 'true';
        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
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
        root.querySelector('[data-notification-read-all]')?.addEventListener('click', async () => {
            await fetch(root.dataset.readAllUrl, { method: 'PATCH', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            await load();
        });
        root.addEventListener('click', async (event) => {
            const link = event.target.closest('[data-notification-id]');
            if (!link || link.dataset.read === 'true') return;
            link.dataset.read = 'true';
            const readUrl = root.dataset.readUrlTemplate.replace('__ID__', encodeURIComponent(link.dataset.notificationId));
            await fetch(readUrl, { method: 'PATCH', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
        });
        void load();
        window.setInterval(load, 15000);
    });
};
