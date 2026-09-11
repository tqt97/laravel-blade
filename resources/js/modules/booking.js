import { formatMoney } from './money.js';

export const initBookingCheckout = () => {
    document.querySelectorAll('[data-booking-checkout]').forEach((checkout) => {
        if (checkout.dataset.bookingCheckoutInitialized === 'true') return;
        checkout.dataset.bookingCheckoutInitialized = 'true';

        const expiresAt = Date.parse(checkout.dataset.expiresAt ?? '');
        const countdown = checkout.querySelector('[data-booking-countdown]');
        const paymentForm = checkout.querySelector('[data-payment-form]');
        if (!countdown || Number.isNaN(expiresAt)) return;

        let timer;
        const tick = () => {
            const serverNow = Date.parse(checkout.dataset.serverNow ?? '');
            const serverClockOffsetMs = Number.isNaN(serverNow) ? 0 : serverNow - Date.now();
            const seconds = Math.max(0, Math.ceil((expiresAt - (Date.now() + serverClockOffsetMs)) / 1000));
            if (checkout.dataset.countdownMode === 'showtime') {
                const days = Math.floor(seconds / 86400);
                const hours = Math.floor((seconds % 86400) / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const parts = [];
                if (days > 0) parts.push(`${days} ${checkout.dataset.dayLabel}`);
                if (hours > 0 || days > 0) parts.push(`${hours} ${checkout.dataset.hourLabel}`);
                parts.push(`${minutes} ${checkout.dataset.minuteLabel}`);
                countdown.textContent = parts.join(' ');
            } else {
                const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
                const remainingSeconds = (seconds % 60).toString().padStart(2, '0');
                countdown.textContent = `${minutes}:${remainingSeconds}`;
            }
            countdown.classList.toggle('text-warning-foreground', seconds > 60 && seconds <= 180);
            countdown.classList.toggle('text-destructive', seconds <= 60);
            countdown.closest('[role="status"]')?.classList.toggle('bg-destructive/10', seconds <= 60);
            countdown.closest('[role="status"]')?.classList.toggle('bg-warning-soft', seconds > 60);

            if (seconds === 0) {
                paymentForm?.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
                if (!paymentForm?.previousElementSibling?.matches('[data-expired-message]')) {
                    paymentForm?.insertAdjacentHTML('beforebegin', `<p data-expired-message class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive" role="alert">${checkout.dataset.expiredLabel ?? ''}</p>`);
                }
                window.clearInterval(timer);
            }
        };

        timer = window.setInterval(tick, 1000);
        tick();

        paymentForm?.addEventListener('submit', () => {
            const button = paymentForm.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.dataset.originalLabel = button.textContent;
            button.textContent = paymentForm.dataset.processingLabel ?? button.textContent;
        }, { once: true });
    });
};

export const initComboTotals = () => {
    document.querySelectorAll('[data-combo-total]').forEach((total) => {
        const form = total.closest('form');
        if (!form || form.dataset.comboTotalsInitialized === 'true') return;
        form.dataset.comboTotalsInitialized = 'true';
        const currency = total.textContent.trim().split(/\s+/).at(-1) ?? '';
        const count = form.querySelector('[data-combo-count]');
        const update = () => {
            let selectedCount = 0;
            const amount = [...form.querySelectorAll('[data-combo-price]')].reduce((sum, input) => {
                let quantity = Math.max(0, Number(input.value ?? 0));
                const max = Number(input.max ?? 0);
                if (quantity > max) {
                    quantity = max;
                    input.value = String(max);
                }
                const status = input.closest('[data-combo-control]')?.querySelector('[data-combo-quantity-status]');
                if (status) status.textContent = status.dataset.selectedLabel.replace(':selected', String(quantity)).replace(':available', status.dataset.availableLabel);
                selectedCount += quantity;
                return sum + Number(input.dataset.comboPrice ?? 0) * quantity;
            }, 0);
            if (count) count.textContent = String(selectedCount);
            total.textContent = formatMoney(amount, currency);
            const checkout = form.closest('[data-booking-checkout]');
            const checkoutComboTotal = checkout?.querySelector('[data-checkout-combo-total]');
            if (checkoutComboTotal) checkoutComboTotal.textContent = formatMoney(amount, currency);
            const grandTotal = checkout?.querySelector('[data-checkout-grand-total]');
            if (grandTotal) {
                const originalComboTotal = Number(form.dataset.originalComboTotal ?? 0);
                const originalGrandTotal = Number(grandTotal.dataset.grandTotal ?? 0);
                grandTotal.textContent = formatMoney(originalGrandTotal + amount - originalComboTotal, currency);
                checkout?.querySelector('[data-mobile-checkout-total]')?.replaceChildren(document.createTextNode(grandTotal.textContent));
            }
        };
        form.addEventListener('input', update);
        form.querySelectorAll('[data-combo-decrease], [data-combo-increase]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.closest('[data-combo-control]')?.querySelector('input');
                if (!input) return;
                const current = Number(input.value ?? 0);
                const step = button.hasAttribute('data-combo-increase') ? 1 : -1;
                const minimum = Number(input.min ?? 0);
                const maximum = Number(input.max ?? 0);
                input.value = String(Math.min(maximum, Math.max(minimum, current + step)));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
        update();

        const checkout = form.closest('[data-booking-checkout]');
        const availabilityUrl = checkout?.dataset.comboAvailabilityUrl;
        let availabilityTimer;
        let availabilityController;
        let lastComboAvailabilityVersion;
        const refreshAvailability = async () => {
            if (!availabilityUrl || document.hidden) return;
            availabilityController?.abort();
            availabilityController = new AbortController();
            try {
                const response = await fetch(availabilityUrl, {headers: {Accept: 'application/json'}, cache: 'no-store', signal: availabilityController.signal});
                if (!response.ok) return;
                const payload = await response.json();
                if (payload.server_now) checkout.dataset.serverNow = payload.server_now;
                if (payload.availability_version && payload.availability_version === lastComboAvailabilityVersion) return;
                lastComboAvailabilityVersion = payload.availability_version;
                Object.entries(payload.concessions ?? {}).forEach(([id, availability]) => {
                    const input = form.querySelector(`input[name="quantities[${id}]"]`);
                    if (!input) return;
                    const card = input.closest('[data-combo-card]');
                    const soldOut = card?.querySelector('[data-combo-sold-out]');
                    const selected = Number(input.value ?? 0);
                    const maximum = Number(availability.max ?? 0);
                    input.max = String(Math.max(selected, maximum));
                    const unavailable = Number(availability.stock) === 0 && selected === 0;
                    input.disabled = unavailable;
                    card?.classList.toggle('opacity-60', unavailable);
                    card?.classList.toggle('grayscale', unavailable);
                    soldOut?.classList.toggle('hidden', !unavailable);
                    const status = card?.querySelector('[data-combo-quantity-status]');
                    if (status) status.dataset.availableLabel = input.max;
                });
                update();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    checkout?.querySelector('[data-availability-status]')?.classList.remove('hidden');
                }
            }
        };
        const scheduleAvailabilityRefresh = () => {
            window.clearTimeout(availabilityTimer);
            if (!document.hidden) {
                availabilityTimer = window.setTimeout(async () => {
                    await refreshAvailability();
                    scheduleAvailabilityRefresh();
                }, 10000);
            }
        };
        document.addEventListener('visibilitychange', scheduleAvailabilityRefresh);
        window.addEventListener('pagehide', () => {
            window.clearTimeout(availabilityTimer);
            availabilityController?.abort();
        }, { once: true });
        void refreshAvailability().finally(scheduleAvailabilityRefresh);
    });
};
