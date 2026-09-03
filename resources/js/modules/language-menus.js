const localeLabels = { vi: 'Tiếng Việt', en: 'English' };

export const initLanguageMenus = () => {
    document.querySelectorAll('[data-language-menu]').forEach((menu) => {
        const trigger = menu.querySelector('[data-language-trigger]');
        const options = menu.querySelector('[data-language-options]');
        const label = menu.querySelector('[data-language-label]');
        const stored = window.localStorage.getItem('app-locale');
        const current = localeLabels[stored] ? stored : (localeLabels[menu.dataset.locale] ? menu.dataset.locale : 'en');
        const setLocale = (locale) => {
            menu.querySelectorAll('[data-language-option]').forEach((option) => option.querySelector('[data-language-check]')?.classList.toggle('hidden', option.dataset.locale !== locale));
            label.textContent = localeLabels[locale];
        };
        const close = () => {
            options.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            menu.querySelector('[data-language-chevron]')?.classList.remove('rotate-180');
        };
        setLocale(current);
        trigger.addEventListener('click', () => {
            options.hidden = !options.hidden;
            trigger.setAttribute('aria-expanded', String(!options.hidden));
            menu.querySelector('[data-language-chevron]')?.classList.toggle('rotate-180', !options.hidden);
        });
        menu.querySelectorAll('[data-language-option]').forEach((option) => option.addEventListener('click', () => {
            const locale = option.dataset.locale;
            option.disabled = true;
            fetch('/locale', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify({ locale }) })
                .then(() => { window.localStorage.setItem('app-locale', locale); window.location.reload(); })
                .catch(() => { option.disabled = false; setLocale(locale); close(); });
            trigger.focus();
        }));
        document.addEventListener('click', (event) => { if (!menu.contains(event.target)) close(); });
    });
};
