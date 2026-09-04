export const initAdminShell = () => {
    const shell = document.querySelector('[data-admin-shell]');
    if (!shell) return;
    const key = 'admin-sidebar-collapsed';
    if (window.localStorage.getItem(key) === 'true') shell.dataset.sidebarCollapsed = 'true';

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => toggle.addEventListener('click', () => {
        const collapsed = shell.dataset.sidebarCollapsed !== 'true';
        shell.dataset.sidebarCollapsed = String(collapsed);
        window.localStorage.setItem(key, String(collapsed));
        toggle.setAttribute('aria-expanded', String(!collapsed));
        const label = collapsed ? toggle.dataset.sidebarExpandLabel : toggle.dataset.sidebarCollapseLabel;
        toggle.setAttribute('title', label ?? 'Sidebar');
        toggle.setAttribute('aria-label', label ?? 'Sidebar');
    }));

    document.querySelectorAll('[data-sidebar-group-button]').forEach((button) => button.addEventListener('click', () => {
        const content = button.closest('[data-sidebar-group]')?.querySelector('[data-sidebar-group-content]');
        const expanded = button.getAttribute('aria-expanded') === 'true';
        if (!content) return;
        button.setAttribute('aria-expanded', String(!expanded));
        content.hidden = expanded;
        button.querySelector('[data-sidebar-chevron]')?.classList.toggle('rotate-180', expanded);
    }));

    document.querySelectorAll('[data-sidebar-mobile-toggle]').forEach((toggle) => toggle.addEventListener('click', () => {
        const open = shell.dataset.mobileSidebarOpen === 'true';
        shell.dataset.mobileSidebarOpen = String(!open);
        toggle.setAttribute('aria-expanded', String(!open));
        document.body.classList.toggle('overflow-hidden', !open);
    }));
    const closeMobileSidebar = () => {
        shell.dataset.mobileSidebarOpen = 'false';
        if (!document.querySelector('[data-modal][data-modal-open="true"]')) {
            document.body.classList.remove('overflow-hidden');
        }
        document.querySelector('[data-sidebar-mobile-toggle]')?.setAttribute('aria-expanded', 'false');
    };
    document.querySelector('[data-sidebar-mobile-close]')?.addEventListener('click', closeMobileSidebar);
    document.querySelector('[data-sidebar-mobile-backdrop]')?.addEventListener('click', closeMobileSidebar);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && shell.dataset.mobileSidebarOpen === 'true') closeMobileSidebar();
    });
};
