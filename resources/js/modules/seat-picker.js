import { formatMoney } from './money.js';

export const initSeatPickers = () => {
    document.querySelectorAll('[data-seat-picker]').forEach((picker) => {
        if (picker.dataset.seatPickerInitialized === 'true') {
            return;
        }

        picker.dataset.seatPickerInitialized = 'true';

        const form = picker.matches('form') ? picker : picker.querySelector('form');
        const buttons = [...picker.querySelectorAll('[data-seat-id]')];
        const count = picker.querySelector('[data-seat-count]');
        const submit = picker.querySelector('[data-seat-submit]');
        const summary = picker.querySelector('[data-seat-summary]');
        const summaryCount = summary?.querySelector('[data-seat-summary-count]');
        const summaryTotal = summary?.querySelector('[data-seat-summary-total]');
        const summaryEmpty = summary?.querySelector('[data-seat-summary-empty]');
        const summarySeats = summary?.querySelector('[data-seat-summary-seats]');
        const summarySeatTotal = summary?.querySelector('[data-seat-summary-seat-total]');
        const summaryComboTotal = summary?.querySelector('[data-seat-summary-combo-total]');
        const comboTotal = picker.querySelector('[data-combo-total]');
        const comboCount = picker.querySelector('[data-combo-count]');
        const suggest = picker.querySelector('[data-seat-suggest]');
        const currency = summary?.dataset.currency ?? '';
        const maxSeatCount = Number(picker.dataset.seatMax ?? 0);
        const combosPerSeat = Number(picker.dataset.combosPerSeat ?? 0);
        const limitStatus = picker.querySelector('[data-seat-limit-status]');
        const comboInputs = [...picker.querySelectorAll('[data-combo-price]')];
        const comboStockLimits = new Map(comboInputs.map((input) => [
            input,
            Number(input.max ?? 0),
        ]));

        if (!form || !count || !submit) {
            return;
        }

        const showLimitMessage = (message) => {
            if (!limitStatus || !message) {
                return;
            }

            limitStatus.textContent = message;
            limitStatus.classList.remove('hidden');
            window.clearTimeout(Number(limitStatus.dataset.hideTimer ?? 0));
            limitStatus.dataset.hideTimer = String(window.setTimeout(() => {
                limitStatus.classList.add('hidden');
            }, 5000));
        };

        const selectedSeatCount = () => buttons.filter(
            (button) => button.dataset.selected === 'true',
        ).length;

        const syncComboLimits = (seatCount) => {
            let clamped = false;
            let selectedComboCount = 0;
            const totalComboLimit = seatCount * combosPerSeat;

            comboInputs.forEach((input) => {
                const stockLimit = comboStockLimits.get(input) ?? 0;
                const maximum = Math.min(stockLimit, Math.max(0, totalComboLimit - selectedComboCount));
                const quantity = Math.max(0, Number(input.value ?? 0));
                const normalizedQuantity = Math.min(quantity, maximum);
                input.max = String(maximum);

                if (quantity !== normalizedQuantity) {
                    input.value = String(normalizedQuantity);
                    clamped = true;
                }
                selectedComboCount += normalizedQuantity;

                const control = input.closest('[data-combo-control]');
                const increase = control?.querySelector('[data-combo-increase]');
                const decrease = control?.querySelector('[data-combo-decrease]');
                if (increase) increase.disabled = input.disabled || Number(input.value) >= maximum;
                if (decrease) decrease.disabled = input.disabled || Number(input.value) <= 0;
                const status = control?.querySelector('[data-combo-quantity-status]');
                if (status) {
                    status.textContent = `${input.value} / ${maximum}`;
                }
            });

            return clamped;
        };

        buttons.forEach((button) => {
            if (button.dataset.seatOwnHold === 'true') {
                button.dataset.selected = 'true';
            }
        });

        const sync = () => {
            picker.querySelectorAll('[data-seat-input]').forEach((input) => input.remove());
            const selected = buttons.filter((button) => button.dataset.selected === 'true');
            const combosClamped = syncComboLimits(selected.length);
            selected.forEach((button) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'seat_ids[]'; input.value = button.dataset.seatId; input.dataset.seatInput = 'true'; form.append(input);
            });
            if (count) count.textContent = String(selected.length);
            if (submit) submit.disabled = selected.length === 0;

                if (summary) {
                const total = selected.reduce((sum, button) => sum + Number(button.dataset.seatPrice ?? 0), 0);
                const selectedCombos = [...picker.querySelectorAll('[data-combo-price]')].reduce((result, input) => ({
                    count: result.count + Math.max(0, Number(input.value ?? 0)),
                    total: result.total + Math.max(0, Number(input.value ?? 0)) * Number(input.dataset.comboPrice ?? 0),
                }), { count: 0, total: 0 });
                const formattedTotal = formatMoney(total, currency);

                if (summaryCount) summaryCount.textContent = String(selected.length);
                if (summaryTotal) summaryTotal.textContent = formattedTotal.trim();
                if (summarySeatTotal) summarySeatTotal.textContent = formattedTotal.trim();
                if (summaryComboTotal) summaryComboTotal.textContent = formatMoney(selectedCombos.total, currency);
                if (comboTotal) comboTotal.textContent = formatMoney(selectedCombos.total, currency);
                if (comboCount) comboCount.textContent = String(selectedCombos.count);
                if (summaryTotal) summaryTotal.textContent = formatMoney(total + selectedCombos.total, currency).trim();
                summaryEmpty?.classList.toggle('hidden', selected.length > 0);
                summarySeats?.classList.toggle('hidden', selected.length === 0);

                if (summarySeats) {
                    summarySeats.replaceChildren(...selected.map((button) => {
                        const item = document.createElement('div');
                        item.className = 'flex items-center justify-between gap-3 rounded-lg bg-card px-3 py-2 text-sm';
                        const label = document.createElement('span');
                        label.className = 'font-semibold';
                        label.textContent = button.dataset.seatLabel ?? button.getAttribute('title') ?? '';
                        const price = document.createElement('span');
                        price.className = 'text-xs text-muted-foreground';
                        price.textContent = formatMoney(Number(button.dataset.seatPrice ?? 0), currency);
                        item.append(label, price);

                        return item;
                    }));
                }
            }

            if (combosClamped) {
                showLimitMessage(picker.dataset.comboSeatLimitLabel ?? 'Combos are limited to three per selected seat.');
            }

            buttons.forEach((button) => {
                const isSelected = button.dataset.selected === 'true';
                const isVip = button.dataset.seatType === 'vip';
                const isAvailable = !button.disabled || isSelected;
                button.classList.remove('border-primary/50', 'bg-primary-soft', 'bg-amber-100', 'text-foreground', 'text-amber-950', 'dark:bg-amber-950/60', 'dark:text-amber-100');
                button.classList.toggle('border-primary', isSelected);
                button.classList.toggle('bg-primary', isSelected);
                button.classList.toggle('text-primary-foreground', isSelected);
                button.classList.toggle('border-amber-400', isAvailable && isVip && !isSelected);
                button.classList.toggle('bg-amber-100', isAvailable && isVip && !isSelected);
                button.classList.toggle('text-amber-950', isAvailable && isVip && !isSelected);
                button.classList.toggle('dark:bg-amber-950/60', isAvailable && isVip && !isSelected);
                button.classList.toggle('dark:text-amber-100', isAvailable && isVip && !isSelected);
                button.classList.toggle('border-primary/50', isAvailable && !isSelected && !isVip);
                button.classList.toggle('bg-primary-soft', isAvailable && !isSelected && !isVip);
                button.classList.toggle('text-foreground', isAvailable && !isSelected && !isVip);
                button.classList.toggle('shadow-md', isSelected);
                button.classList.toggle('ring-2', isSelected);
                button.classList.toggle('ring-primary/30', isSelected);
                button.classList.toggle('scale-[1.03]', isSelected);
                button.setAttribute('aria-pressed', String(isSelected));

                const indicator = button.querySelector('[data-seat-selected-indicator]');
                indicator?.classList.toggle('hidden', !isSelected);
            });
        };
        const refreshAvailability = async (signal) => {
            const url = picker.dataset.seatAvailabilityUrl;
            if (!url || document.hidden) return;
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal });
                if (!response.ok) return;
                const data = await response.json();
                let changed = false;
                buttons.forEach((button) => {
                    const state = data.seats?.[button.dataset.seatId];
                    const available = typeof state === 'boolean' ? state : state?.available === true;
                    const ownedByCurrentBooking = typeof state === 'object' && state?.owned_by_current_booking === true;
                    button.dataset.seatOwnHold = String(ownedByCurrentBooking);
                    const selectable = available || ownedByCurrentBooking;
                    if (!selectable && button.dataset.selected === 'true') {
                        button.dataset.selected = 'false';
                        changed = true;
                    }
                    if (!button.disabled || button.dataset.selected !== 'true') {
                        button.disabled = !selectable;
                        button.classList.toggle('cursor-not-allowed', !selectable);
                        button.classList.toggle('line-through', !selectable);
                    }
                });
                if (changed) {
                    sync();
                    const notice = document.createElement('p');
                    notice.className = 'rounded-xl bg-warning-soft p-4 text-sm text-warning-foreground';
                    notice.setAttribute('role', 'alert');
                    notice.textContent = picker.dataset.seatConflictLabel ?? '';
                    picker.before(notice);
                    window.setTimeout(() => notice.remove(), 6000);
                }
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    picker.querySelector('[data-availability-status]')?.classList.remove('hidden');
                }
            }
        };
        const openConfirmation = () => {
            const selected = buttons.filter((button) => button.dataset.selected === 'true');
            if (selected.length === 0) {
                return;
            }

            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 z-50 grid place-items-center bg-black/60 p-4 backdrop-blur-sm';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            const dialogId = `seat-confirm-${Date.now()}`;
            modal.setAttribute('aria-labelledby', `${dialogId}-title`);
            modal.setAttribute('aria-describedby', `${dialogId}-description`);

            const panel = document.createElement('div');
            panel.className = 'w-full max-w-2xl overflow-hidden rounded-2xl border border-border bg-card shadow-xl shadow-black/20';

            const header = document.createElement('div');
            header.className = 'flex items-start gap-3 border-b border-border px-5 py-4 sm:px-6';
            const icon = document.createElement('div');
            icon.className = 'grid size-10 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary';
            icon.innerHTML = '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>';
            const title = document.createElement('h2');
            title.className = 'min-w-0 flex-1 pt-1 text-base font-semibold text-card-foreground sm:text-lg';
            title.id = `${dialogId}-title`;
            title.textContent = picker.dataset.seatConfirmTitle ?? 'Confirm seats';
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'rounded-lg p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground';
            close.setAttribute('aria-label', picker.dataset.seatConfirmCancel ?? 'Cancel');
            close.innerHTML = '<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>';
            header.append(icon, title, close);

            const content = document.createElement('div');
            content.className = 'max-h-[min(70vh,38rem)] space-y-4 overflow-y-auto px-5 py-4 sm:px-6 sm:py-5';
            const description = document.createElement('p');
            description.className = 'text-sm leading-6 text-muted-foreground';
            description.id = `${dialogId}-description`;
            description.textContent = picker.dataset.seatConfirmDescription ?? 'Review your selected seats before continuing.';
            const createHeading = (label) => {
                const heading = document.createElement('h3');
                heading.className = 'text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground';
                heading.textContent = label;
                return heading;
            };
            const seatList = document.createElement('div');
            seatList.className = 'space-y-2';
            const seatGroups = selected.reduce((groups, button) => {
                const price = Number(button.dataset.seatPrice ?? 0);
                const group = groups.get(price) ?? { labels: [], price };
                group.labels.push(button.dataset.seatLabel ?? button.getAttribute('title') ?? button.textContent.trim());
                groups.set(price, group);

                return groups;
            }, new Map());
            seatGroups.forEach((group) => {
                const seat = document.createElement('div');
                seat.className = 'flex items-center justify-between gap-4 rounded-lg bg-primary-soft px-3 py-2.5 text-sm';
                const label = document.createElement('span');
                label.className = 'font-semibold text-primary';
                label.textContent = `${group.labels.join(', ')} × ${group.labels.length}`;
                const price = document.createElement('span');
                price.className = 'font-bold text-primary';
                price.textContent = formatMoney(group.price * group.labels.length, currency);
                seat.append(label, price);
                seatList.append(seat);
            });
            const comboInputs = [...picker.querySelectorAll('[data-combo-price]')].filter((input) => Number(input.value ?? 0) > 0);
            const comboTotalAmount = comboInputs.reduce((sum, input) => sum + Number(input.value) * Number(input.dataset.comboPrice ?? 0), 0);
            const seatTotalAmount = selected.reduce((sum, button) => sum + Number(button.dataset.seatPrice ?? 0), 0);
            const totalAmount = seatTotalAmount + comboTotalAmount;
            const screeningInfo = document.createElement('div');
            screeningInfo.className = 'grid gap-3 rounded-xl border border-border bg-muted/60 p-3.5 text-sm sm:grid-cols-3';
            const addInfo = (label, value) => {
                if (!value) return;
                const item = document.createElement('div');
                item.className = 'min-w-0';
                const itemLabel = document.createElement('p');
                itemLabel.className = 'text-xs text-muted-foreground';
                itemLabel.textContent = label;
                const itemValue = document.createElement('p');
                itemValue.className = 'mt-1 truncate font-semibold text-foreground';
                itemValue.textContent = value;
                item.append(itemLabel, itemValue);
                screeningInfo.append(item);
            };
            addInfo(picker.dataset.seatConfirmMovieLabel ?? 'Movie', picker.dataset.seatConfirmMovie);
            addInfo(picker.dataset.seatConfirmShowtimeLabel ?? 'Showtime', picker.dataset.seatConfirmShowtime);
            addInfo(picker.dataset.seatConfirmRoomLabel ?? 'Room', picker.dataset.seatConfirmRoom);
            const priceSummary = document.createElement('dl');
            priceSummary.className = 'space-y-2 rounded-xl border border-primary/20 bg-primary-soft/50 p-3 text-sm';
            const addPriceRow = (label, amount, emphasized = false) => {
                const row = document.createElement('div');
                row.className = `flex items-center justify-between gap-4 ${emphasized ? 'border-t border-primary/20 pt-3 font-bold' : ''}`;
                const term = document.createElement('dt');
                term.className = emphasized ? 'text-base text-foreground' : 'text-muted-foreground';
                term.textContent = label;
                const value = document.createElement('dd');
                value.className = emphasized ? 'text-lg font-extrabold text-primary' : 'font-bold text-foreground';
                value.textContent = formatMoney(amount, currency);
                row.append(term, value);
                priceSummary.append(row);
            };
            addPriceRow(picker.dataset.seatConfirmSeats ?? 'Seats', seatTotalAmount);
            content.append(description);
            if (screeningInfo.childElementCount > 0) content.append(screeningInfo);
            content.append(createHeading(picker.dataset.seatConfirmSeats ?? 'Seats'), seatList);
            if (comboInputs.length > 0) {
                const comboList = document.createElement('div');
                comboList.className = 'space-y-2 rounded-xl border border-border bg-background p-3';
                comboInputs.forEach((input) => {
                    const row = document.createElement('div');
                    row.className = 'flex items-center justify-between gap-3 text-sm';
                    const label = document.createElement('span');
                    label.className = 'font-medium';
                    label.textContent = `${input.dataset.comboName ?? 'Combo'} × ${input.value}`;
                    const value = document.createElement('span');
                    value.className = 'font-bold text-foreground';
                    value.textContent = formatMoney(Number(input.value) * Number(input.dataset.comboPrice ?? 0), currency);
                    row.append(label, value);
                    comboList.append(row);
                });
                content.append(createHeading(picker.dataset.seatConfirmCombos ?? 'Combos'), comboList);
            }
            addPriceRow(picker.dataset.seatConfirmTotal ?? 'Total', totalAmount, true);
            content.append(priceSummary);

            const footer = document.createElement('div');
            footer.className = 'flex justify-end gap-2 border-t border-border bg-muted/50 px-5 py-3 sm:px-6';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border bg-secondary px-3.5 py-2 text-sm font-semibold text-secondary-foreground transition hover:bg-muted';
            cancel.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>';
            cancel.append(document.createTextNode(picker.dataset.seatConfirmCancel ?? 'Cancel'));
            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-3.5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-strong';
            confirm.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>';
            confirm.append(document.createTextNode(picker.dataset.seatConfirmLabel ?? 'Confirm and continue'));
            footer.append(cancel, confirm);
            panel.append(header, content, footer);
            modal.append(panel);
            document.body.append(modal);
            document.body.classList.add('overflow-hidden');

            const previousFocus = document.activeElement;
            const closeConfirmation = () => {
                modal.remove();
                if (!document.querySelector('[role="dialog"]')) {
                    document.body.classList.remove('overflow-hidden');
                }
                previousFocus?.focus();
                document.removeEventListener('keydown', onKeydown);
            };
            const onKeydown = (event) => {
                if (event.key === 'Escape') {
                    closeConfirmation();
                    return;
                }
                if (event.key === 'Tab') {
                    const focusable = [...modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])')];
                    if (focusable.length === 0) return;
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            };

            close.addEventListener('click', closeConfirmation);
            cancel.addEventListener('click', closeConfirmation);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeConfirmation();
                }
            });
            confirm.addEventListener('click', () => {
                picker.dataset.seatConfirmed = 'true';
                confirm.disabled = true;
                HTMLFormElement.prototype.submit.call(form);
            });
            document.addEventListener('keydown', onKeydown);
            close.focus();
        };

        form.addEventListener('submit', (event) => {
            if (picker.dataset.seatConfirmed === 'true') {
                return;
            }

            event.preventDefault();
            openConfirmation();
        });

        buttons.forEach((button) => button.addEventListener('click', () => {
            const selected = button.dataset.selected === 'true';
            if (!selected && selectedSeatCount() >= maxSeatCount) {
                showLimitMessage(picker.dataset.seatLimitLabel ?? `You can select up to ${maxSeatCount} seats.`);
                return;
            }
            button.dataset.selected = String(!selected);
            sync();
        }));

        picker.querySelectorAll('[data-combo-price]').forEach((input) => {
            input.addEventListener('input', () => {
                const quantity = Math.max(0, Number(input.value ?? 0));
                sync();
                if (quantity > Number(input.value ?? 0)) {
                    showLimitMessage(picker.dataset.comboSeatLimitLabel ?? 'Combos are limited to three per selected seat.');
                }
            });
        });
        picker.querySelectorAll('[data-combo-decrease], [data-combo-increase]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.closest('[data-combo-control]')?.querySelector('input');
                if (!input || input.disabled) return;
                const current = Number(input.value ?? 0);
                const step = button.hasAttribute('data-combo-increase') ? 1 : -1;
                const minimum = Number(input.min ?? 0);
                const maximum = Number(input.max ?? 0);
                const comboMaximum = maximum;
                if (step > 0 && current >= comboMaximum) {
                    showLimitMessage(picker.dataset.comboSeatLimitLabel ?? 'Combos are limited to three per selected seat.');
                    return;
                }
                input.value = String(Math.min(maximum, Math.max(minimum, current + step)));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        suggest?.addEventListener('click', () => {
            const selectedCount = Math.max(1, buttons.filter((button) => button.dataset.selected === 'true').length);
            const rows = [...new Set(buttons.map((button) => button.closest('.flex.items-center') ?? button.parentElement))];
            const row = rows.find((candidate) => [...candidate.querySelectorAll('[data-seat-id]')].filter((button) => !button.disabled).length >= selectedCount);
            const candidates = row ? [...row.querySelectorAll('[data-seat-id]')].filter((button) => !button.disabled) : buttons.filter((button) => !button.disabled);
            buttons.forEach((button) => { button.dataset.selected = 'false'; });
            candidates.slice(0, selectedCount).forEach((button) => { button.dataset.selected = 'true'; });
            sync();
        });

        let availabilityTimer;
        let availabilityController;
        const refresh = async () => {
            availabilityController?.abort();
            availabilityController = new AbortController();
            await refreshAvailability(availabilityController.signal);
            if (!document.hidden) {
                availabilityTimer = window.setTimeout(refresh, 10000);
            }
        };
        document.addEventListener('visibilitychange', () => {
            window.clearTimeout(availabilityTimer);
            if (!document.hidden) void refresh();
        });
        window.addEventListener('pagehide', () => {
            window.clearTimeout(availabilityTimer);
            availabilityController?.abort();
        }, { once: true });
        sync();
        void refresh();
    });
};
