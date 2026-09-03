const themeStorageKey = 'app-theme';

export const initTheme = () => {
    const getPreferredTheme = () => {
        const storedTheme = window.localStorage.getItem(themeStorageKey);

        if (storedTheme === 'light' || storedTheme === 'dark') return storedTheme;

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    const setTheme = (theme) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.dataset.theme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-pressed', String(theme === 'dark'));
            const label = theme === 'dark' ? toggle.dataset.themeToLight : toggle.dataset.themeToDark;
            toggle.setAttribute('title', label ?? 'Theme');
            toggle.setAttribute('aria-label', label ?? 'Theme');
        });
    };

    setTheme(getPreferredTheme());
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-theme-toggle]');
        if (!toggle) return;
        const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        window.localStorage.setItem(themeStorageKey, nextTheme);
        setTheme(nextTheme);
    });
};

export const initPasswordControls = () => {
    document.addEventListener('click', (event) => {
        const passwordToggle = event.target.closest('[data-password-toggle]');
        if (passwordToggle) {
            const password = document.getElementById(passwordToggle.dataset.passwordToggle);
            if (!password) return;
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(isPassword));
            passwordToggle.setAttribute('title', isPassword ? passwordToggle.dataset.passwordHide : passwordToggle.dataset.passwordShow);
            passwordToggle.innerHTML = isPassword
                ? '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A10.8 10.8 0 0 1 12 4c5 0 8.8 3.5 10 8a11.7 11.7 0 0 1-3.2 5.5M6.6 6.6A11.8 11.8 0 0 0 2 12c1.2 4.5 5 8 10 8 1.3 0 2.5-.2 3.6-.7"/></svg>'
                : '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>';
        }

        const generatePassword = event.target.closest('[data-password-generate]');
        if (!generatePassword) return;
        const password = document.getElementById(generatePassword.dataset.passwordGenerate);
        if (!password) return;
        const characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
        const randomValues = new Uint32Array(18);
        window.crypto.getRandomValues(randomValues);
        password.value = Array.from(randomValues, (value) => characters[value % characters.length]).join('');
        password.type = 'text';
        password.dispatchEvent(new Event('input', { bubbles: true }));
        password.focus();
    });
};

export const closeModal = (modal) => {
    if (!modal) return;
    modal.hidden = true;
    modal.removeAttribute('data-modal-open');
    modal._previousFocus?.focus();
};

export const initModals = () => {
    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.modalOpen);
            if (!modal) return;
            const isBulk = trigger.dataset.bulkAction !== undefined;
            const selectedIds = isBulk
                ? [...document.querySelectorAll('[data-user-selection]:checked')].map((input) => input.value)
                : [];
            if (isBulk && selectedIds.length === 0) return;
            const confirmButton = modal.querySelector('[data-modal-confirm]');
            modal._previousFocus = document.activeElement;
            modal.hidden = false;
            modal.setAttribute('data-modal-open', 'true');
            if (confirmButton) {
                confirmButton.dataset.action = trigger.dataset.modalAction ?? '';
                confirmButton.dataset.method = trigger.dataset.modalMethod ?? 'POST';
                confirmButton.dataset.bulkIds = JSON.stringify(selectedIds);
            }
            const title = modal.querySelector('[data-modal-title]');
            const description = modal.querySelector('[data-modal-description]');
            if (title && trigger.dataset.modalTitle) title.textContent = trigger.dataset.modalTitle;
            if (description && trigger.dataset.modalDescription) description.textContent = trigger.dataset.modalDescription;
            if (confirmButton && trigger.dataset.modalConfirmLabel) {
                confirmButton.querySelector('[data-modal-label]')?.replaceChildren(trigger.dataset.modalConfirmLabel);
            }
            modal.querySelector('[data-modal-close], [data-modal-confirm]')?.focus();
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal(modal);
        });
        modal.querySelector('[data-modal-confirm]')?.addEventListener('click', (event) => {
            const confirmButton = event.currentTarget;
            if (!confirmButton.dataset.action) return closeModal(modal);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = confirmButton.dataset.action;
            form.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content ?? ''}">`;
            if (confirmButton.dataset.method?.toUpperCase() !== 'POST') {
                form.insertAdjacentHTML('beforeend', `<input type="hidden" name="_method" value="${confirmButton.dataset.method}">`);
            }
            let selectedIds = [];
            try {
                selectedIds = JSON.parse(confirmButton.dataset.bulkIds ?? '[]');
            } catch {
                selectedIds = [];
            }
            selectedIds.forEach((id) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.append(input);
            });
            confirmButton.disabled = true;
            document.body.append(form);
            form.submit();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') document.querySelectorAll('[data-modal][data-modal-open="true"]').forEach(closeModal);
    });
};

export const initToasts = () => {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const dismiss = () => {
            toast.classList.add('pointer-events-none', 'opacity-0', 'translate-y-2');
            window.setTimeout(() => toast.remove(), 200);
        };
        toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', dismiss);
        if (Number(toast.dataset.toastTimeout) > 0) window.setTimeout(dismiss, Number(toast.dataset.toastTimeout));
    });
};
