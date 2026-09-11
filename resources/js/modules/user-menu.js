export const initUserMenus = () => {
    document.querySelectorAll('[data-user-menu]').forEach((menu) => {
        if (menu.dataset.initialized === 'true') return;
        menu.dataset.initialized = 'true';

        const summary = menu.querySelector('summary');
        const close = () => {
            if (!menu.open) return;
            menu.removeAttribute('open');
            summary?.focus();
        };

        document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) close();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && menu.open) close();
        });
    });
};
