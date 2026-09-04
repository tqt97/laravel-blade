export function initUserShell() {
    const shell = document.querySelector('[data-user-shell]');
    if (!shell) return;

    const sidebar = shell.querySelector('aside');
    const backdrop = shell.querySelector('[data-user-sidebar-backdrop]');
    const setOpen = (open) => {
        shell.dataset.mobileSidebarOpen = String(open);
        sidebar?.classList.toggle('-translate-x-full', !open);
        backdrop?.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
    };

    shell.querySelector('[data-user-sidebar-toggle]')?.addEventListener('click', () => setOpen(true));
    shell.querySelector('[data-user-sidebar-close]')?.addEventListener('click', () => setOpen(false));
    backdrop?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
}
