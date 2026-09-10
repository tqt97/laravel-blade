const poll = (root) => {
    const statusUrl = root.dataset.statusUrl;
    let timer;
    const terminalStatuses = new Set(['failed', 'refunded', 'requires_refund', 'canceled']);

    const check = async () => {
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                timer = window.setTimeout(check, 5000);
                return;
            }
            const data = await response.json();
            if (data.redirect) {
                window.location.assign(data.redirect);
                return;
            }
            if (terminalStatuses.has(data.status)) {
                return;
            }
            timer = window.setTimeout(check, 3000);
        } catch {
            timer = window.setTimeout(check, 5000);
        }
    };

    check();
    return () => window.clearTimeout(timer);
};

export const initPaymentStatus = () => {
    document.querySelectorAll('[data-payment-status]').forEach((root) => {
        if (root.dataset.initialized === 'true') return;
        root.dataset.initialized = 'true';
        const stopPolling = poll(root);
        const button = root.querySelector('[data-stripe-confirm]');
        button?.addEventListener('click', async () => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const stripeKey = root.dataset.stripeKey;
            const clientSecret = root.dataset.clientSecret;
            const showError = (message) => {
                const error = root.querySelector('[data-payment-error]');
                if (!error) return;
                error.textContent = message;
                error.classList.remove('hidden');
            };
            if (!window.Stripe || !stripeKey || !clientSecret) {
                showError(root.dataset.unavailableLabel ?? '');
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
                return;
            }
            try {
                const result = await window.Stripe(stripeKey).confirmCardPayment(clientSecret);
                if (result.error) {
                    showError(result.error.message ?? root.dataset.errorLabel ?? root.dataset.unavailableLabel ?? '');
                }
            } catch {
                showError(root.dataset.errorLabel ?? root.dataset.unavailableLabel ?? '');
            } finally {
                button.disabled = false;
                button.setAttribute('aria-busy', 'false');
            }
        });
        window.addEventListener('beforeunload', stopPolling, { once: true });
    });
};
