const themeStorageKey = 'app-theme';

const getPreferredTheme = () => {
    const storedTheme = window.localStorage.getItem(themeStorageKey);

    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const setTheme = (theme) => {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.dataset.theme = theme;

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
        toggle.setAttribute('title', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
        toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
    });
};

setTheme(getPreferredTheme());

document.addEventListener('click', (event) => {
    const themeToggle = event.target.closest('[data-theme-toggle]');

    if (themeToggle) {
        const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

        window.localStorage.setItem(themeStorageKey, nextTheme);
        setTheme(nextTheme);
    }

    const passwordToggle = event.target.closest('[data-password-toggle]');

    if (passwordToggle) {
        const password = document.getElementById(passwordToggle.dataset.passwordToggle);

        if (!password) {
            return;
        }

        const isPassword = password.type === 'password';
        password.type = isPassword ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(isPassword));
        passwordToggle.setAttribute('title', isPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        passwordToggle.innerHTML = isPassword
            ? '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A10.8 10.8 0 0 1 12 4c5 0 8.8 3.5 10 8a11.7 11.7 0 0 1-3.2 5.5M6.6 6.6A11.8 11.8 0 0 0 2 12c1.2 4.5 5 8 10 8 1.3 0 2.5-.2 3.6-.7"/></svg>'
            : '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>';
    }

    const generatePassword = event.target.closest('[data-password-generate]');

    if (generatePassword) {
        const password = document.getElementById(generatePassword.dataset.passwordGenerate);

        if (!password) {
            return;
        }

        const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
        const randomValues = new Uint32Array(18);

        window.crypto.getRandomValues(randomValues);
        password.value = Array.from(randomValues, (value) => characters[value % characters.length]).join('');
        password.type = 'text';
        password.dispatchEvent(new Event('input', { bubbles: true }));
        password.focus();
    }
});

const localeLabels = { vi: 'Tiếng Việt', en: 'English' };

document.querySelectorAll('[data-language-menu]').forEach((menu) => {
    const trigger = menu.querySelector('[data-language-trigger]');
    const options = menu.querySelector('[data-language-options]');
    const label = menu.querySelector('[data-language-label]');
    const storedLocale = window.localStorage.getItem('app-locale');
    const serverLocale = menu.dataset.locale;
    const currentLocale = localeLabels[storedLocale] ? storedLocale : (localeLabels[serverLocale] ? serverLocale : 'en');

    const setLocale = (locale) => {
        menu.querySelectorAll('[data-language-option]').forEach((option) => {
            option.querySelector('[data-language-check]')?.classList.toggle('hidden', option.dataset.locale !== locale);
        });
        label.textContent = localeLabels[locale];
    };

    const close = () => {
        options.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        menu.querySelector('[data-language-chevron]')?.classList.remove('rotate-180');
    };

    setLocale(currentLocale);

    trigger.addEventListener('click', () => {
        options.hidden = !options.hidden;
        trigger.setAttribute('aria-expanded', String(!options.hidden));
        menu.querySelector('[data-language-chevron]')?.classList.toggle('rotate-180', !options.hidden);
        if (!options.hidden) {
            menu.querySelector(`[data-locale="${storedLocale || currentLocale}"]`)?.focus();
        }
    });

    menu.querySelectorAll('[data-language-option]').forEach((option) => {
        option.addEventListener('click', () => {
            const locale = option.dataset.locale;
            option.disabled = true;
            fetch('/locale', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ locale }),
            }).then(() => {
                window.localStorage.setItem('app-locale', locale);
                window.location.reload();
            }).catch(() => {
                option.disabled = false;
                setLocale(locale);
                close();
                window.dispatchEvent(new CustomEvent('locale:change', { detail: { locale } }));
            });
            trigger.focus();
        });
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            close();
        }
    });
});

const adminShell = document.querySelector('[data-admin-shell]');

if (adminShell) {
    const sidebarStateKey = 'admin-sidebar-collapsed';
    const savedSidebarState = window.localStorage.getItem(sidebarStateKey);

    if (savedSidebarState === 'true') {
        adminShell.dataset.sidebarCollapsed = 'true';
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const collapsed = adminShell.dataset.sidebarCollapsed !== 'true';

            adminShell.dataset.sidebarCollapsed = String(collapsed);
            window.localStorage.setItem(sidebarStateKey, String(collapsed));
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        });
    });

    document.querySelectorAll('[data-sidebar-group-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.closest('[data-sidebar-group]');
            const content = group?.querySelector('[data-sidebar-group-content]');
            const expanded = button.getAttribute('aria-expanded') === 'true';

            if (!content) {
                return;
            }

            button.setAttribute('aria-expanded', String(!expanded));
            content.hidden = expanded;
            button.querySelector('[data-sidebar-chevron]')?.classList.toggle('rotate-180', expanded);
        });
    });

    document.querySelectorAll('[data-sidebar-mobile-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const open = adminShell.dataset.mobileSidebarOpen === 'true';

            adminShell.dataset.mobileSidebarOpen = String(!open);
            toggle.setAttribute('aria-expanded', String(!open));
        });
    });

    document.querySelector('[data-sidebar-mobile-close]')?.addEventListener('click', () => {
        adminShell.dataset.mobileSidebarOpen = 'false';
    });

    document.querySelector('[data-sidebar-mobile-backdrop]')?.addEventListener('click', () => {
        adminShell.dataset.mobileSidebarOpen = 'false';
    });
}

const closeModal = (modal) => {
    if (!modal) {
        return;
    }

    modal.hidden = true;
    modal.removeAttribute('data-modal-open');
    modal._previousFocus?.focus();
};

document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const modal = document.getElementById(trigger.dataset.modalOpen);

        if (!modal) {
            return;
        }

        modal._previousFocus = document.activeElement;
        modal.hidden = false;
        modal.setAttribute('data-modal-open', 'true');
        modal.querySelector('[data-modal-close], [data-modal-confirm]')?.focus();
    });
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((closeButton) => {
        closeButton.addEventListener('click', () => closeModal(modal));
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal(modal);
        }
    });

    modal.querySelector('[data-modal-confirm]')?.addEventListener('click', (event) => {
        const confirmButton = event.currentTarget;
        const action = confirmButton.dataset.action;

        if (!action) {
            closeModal(modal);
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content ?? ''}">`;

        if (confirmButton.dataset.method?.toUpperCase() !== 'POST') {
            form.insertAdjacentHTML('beforeend', `<input type="hidden" name="_method" value="${confirmButton.dataset.method}">`);
        }

        document.body.append(form);
        form.submit();
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-modal][data-modal-open="true"]').forEach(closeModal);
    }
});

document.querySelectorAll('[data-toast]').forEach((toast) => {
    const dismiss = () => {
        toast.classList.add('pointer-events-none', 'opacity-0', 'translate-y-2');
        window.setTimeout(() => toast.remove(), 200);
    };

    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', dismiss);

    if (Number(toast.dataset.toastTimeout) > 0) {
        window.setTimeout(dismiss, Number(toast.dataset.toastTimeout));
    }
});
