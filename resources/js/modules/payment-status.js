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
                root.dispatchEvent(new CustomEvent('payment:terminal', { detail: data.status }));
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
        const stripeKey = root.dataset.stripeKey;
        const clientSecret = root.dataset.clientSecret;
        let stripe;
        let elements;
        const showError = (message) => {
            const error = root.querySelector('[data-payment-error]');
            if (!error) return;
            error.textContent = message;
            error.classList.remove('hidden');
        };
        const loading = root.querySelector('[data-payment-loading]');
        const processing = root.querySelector('[data-payment-processing]');
        const processingText = root.querySelector('[data-payment-processing-text]');
        const processingDelayed = root.querySelector('[data-payment-processing-delayed]');
        const buttonLabel = root.querySelector('[data-payment-button-label]');
        const buttonSpinner = root.querySelector('[data-payment-button-spinner]');
        let delayedTimer;
        const setSubmitting = (isSubmitting) => {
            if (!button) return;
            button.disabled = isSubmitting;
            button.setAttribute('aria-busy', String(isSubmitting));
            buttonLabel?.classList.toggle('hidden', isSubmitting);
            buttonSpinner?.classList.toggle('hidden', !isSubmitting);
        };
        const setProcessing = (isProcessing, message = root.dataset.processingLabel ?? '') => {
            if (!processing) return;
            processing.classList.toggle('hidden', !isProcessing);
            if (processingText && message) processingText.textContent = message;
            processingDelayed?.classList.toggle('hidden', !isProcessing);
            window.clearTimeout(delayedTimer);
            if (isProcessing && processingDelayed) {
                delayedTimer = window.setTimeout(() => {
                    processingDelayed.classList.remove('hidden');
                }, 45000);
            }
        };
        root.addEventListener('payment:terminal', () => {
            setSubmitting(false);
            setProcessing(false);
            showError(root.dataset.errorLabel ?? '');
        }, { once: true });

        try {
            if (window.Stripe && stripeKey && clientSecret) {
                stripe = window.Stripe(stripeKey);
                elements = stripe.elements({ clientSecret });
                const paymentElement = root.querySelector('[data-payment-element]');
                if (paymentElement) {
                    elements.create('payment').mount(paymentElement);
                }
            }
            loading?.classList.add('hidden');
        } catch {
            loading?.classList.add('hidden');
            showError(root.dataset.unavailableLabel ?? '');
        }
        root.querySelectorAll('[data-copy-test-card]').forEach((copyButton) => {
            copyButton.addEventListener('click', async () => {
                const cardNumber = copyButton.dataset.copyTestCard;
                if (!cardNumber) return;
                try {
                    await navigator.clipboard.writeText(cardNumber);
                    const status = root.querySelector('[data-copy-test-card-status]');
                    if (!status) return;
                    status.textContent = root.dataset.copySuccessLabel ?? '';
                    status.classList.remove('hidden');
                } catch {
                    showError(root.dataset.unavailableLabel ?? '');
                }
            });
        });
        button?.addEventListener('click', async () => {
            setSubmitting(true);
            setProcessing(true);
            if (!stripe || !elements) {
                showError(root.dataset.unavailableLabel ?? '');
                setSubmitting(false);
                setProcessing(false);
                return;
            }
            try {
                const result = await stripe.confirmPayment({
                    elements,
                    confirmParams: { return_url: root.dataset.returnUrl },
                    redirect: 'if_required',
                });
                if (result.error) {
                    showError(result.error.message ?? root.dataset.errorLabel ?? root.dataset.unavailableLabel ?? '');
                    setSubmitting(false);
                    setProcessing(false);
                }
            } catch {
                showError(root.dataset.errorLabel ?? root.dataset.unavailableLabel ?? '');
                setSubmitting(false);
                setProcessing(false);
            }
        });
        window.addEventListener('beforeunload', () => {
            stopPolling();
            window.clearTimeout(delayedTimer);
        }, { once: true });
    });
};
