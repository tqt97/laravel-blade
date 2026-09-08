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
        const currency = summary?.dataset.currency ?? '';
        const locale = document.documentElement.lang === 'vi' ? 'vi-VN' : 'en-US';

        if (!form || !count || !submit) {
            return;
        }

        const sync = () => {
            picker.querySelectorAll('[data-seat-input]').forEach((input) => input.remove());
            const selected = buttons.filter((button) => button.dataset.selected === 'true');
            selected.forEach((button) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'seat_ids[]'; input.value = button.dataset.seatId; input.dataset.seatInput = 'true'; form.append(input);
            });
            if (count) count.textContent = String(selected.length);
            if (submit) submit.disabled = selected.length === 0;

            if (summary) {
                const total = selected.reduce((sum, button) => sum + Number(button.dataset.seatPrice ?? 0), 0);
                const formattedTotal = new Intl.NumberFormat(locale).format(total) + ' ' + currency;

                if (summaryCount) summaryCount.textContent = String(selected.length);
                if (summaryTotal) summaryTotal.textContent = formattedTotal.trim();
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
                        price.textContent = new Intl.NumberFormat(locale).format(Number(button.dataset.seatPrice ?? 0)) + ' ' + currency;
                        item.append(label, price);

                        return item;
                    }));
                }
            }

            buttons.forEach((button) => {
                const isSelected = button.dataset.selected === 'true';
                button.classList.toggle('border-primary', isSelected);
                button.classList.toggle('bg-primary', isSelected);
                button.classList.toggle('text-primary-foreground', isSelected);
                button.classList.toggle('shadow-md', isSelected);
                button.classList.toggle('ring-2', isSelected);
                button.classList.toggle('ring-primary/30', isSelected);
                button.classList.toggle('scale-[1.03]', isSelected);
                button.setAttribute('aria-pressed', String(isSelected));

                const indicator = button.querySelector('[data-seat-selected-indicator]');
                indicator?.classList.toggle('hidden', !isSelected);
            });
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

            const panel = document.createElement('div');
            panel.className = 'w-full max-w-md overflow-hidden rounded-2xl border border-border bg-card shadow-xl shadow-black/20';

            const header = document.createElement('div');
            header.className = 'flex items-start gap-4 border-b border-border p-6';
            const icon = document.createElement('div');
            icon.className = 'grid size-11 shrink-0 place-items-center rounded-xl bg-primary-soft text-primary';
            icon.textContent = '✓';
            const title = document.createElement('h2');
            title.className = 'min-w-0 flex-1 pt-1 text-lg font-semibold text-card-foreground';
            title.textContent = picker.dataset.seatConfirmTitle ?? 'Confirm seats';
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'rounded-lg p-2 text-muted-foreground transition hover:bg-accent hover:text-foreground';
            close.setAttribute('aria-label', picker.dataset.seatConfirmCancel ?? 'Cancel');
            close.textContent = '×';
            header.append(icon, title, close);

            const content = document.createElement('div');
            content.className = 'space-y-4 p-6';
            const description = document.createElement('p');
            description.className = 'text-sm leading-6 text-muted-foreground';
            description.textContent = picker.dataset.seatConfirmDescription ?? 'Review your selected seats before continuing.';
            const seatList = document.createElement('div');
            seatList.className = 'flex flex-wrap gap-2';
            selected.forEach((button) => {
                const seat = document.createElement('span');
                seat.className = 'rounded-lg bg-primary-soft px-3 py-2 text-sm font-semibold text-primary';
                seat.textContent = button.getAttribute('title') ?? button.textContent.trim();
                seatList.append(seat);
            });
            content.append(description, seatList);

            const footer = document.createElement('div');
            footer.className = 'flex justify-end gap-2 border-t border-border bg-muted/50 p-6';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'inline-flex min-h-10 items-center justify-center rounded-lg border border-border bg-secondary px-3.5 py-2 text-sm font-semibold text-secondary-foreground transition hover:bg-muted';
            cancel.textContent = picker.dataset.seatConfirmCancel ?? 'Cancel';
            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-3.5 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary-strong';
            confirm.textContent = picker.dataset.seatConfirmLabel ?? 'Confirm and continue';
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
            button.dataset.selected = String(!selected);
            sync();
        }));

        sync();
    });
};
