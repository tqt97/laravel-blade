const toCalendarDate = (value) => new Date(value).toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');

export const initTicketActions = () => {
    document.querySelectorAll('[data-ticket-card]').forEach((card) => {
        const qr = card.querySelector('.size-60');
        card.querySelector('[data-ticket-download]')?.addEventListener('click', () => {
            if (!qr) return;
            const blob = new Blob([qr.innerHTML], { type: 'image/svg+xml' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob); link.download = 'ticket-qr.svg'; link.click(); URL.revokeObjectURL(link.href);
        });
        card.querySelector('[data-ticket-share]')?.addEventListener('click', async () => {
            const data = { title: card.dataset.shareTitle ?? 'Ticket', text: card.dataset.shareTitle ?? 'My cinema ticket', url: window.location.href };
            if (navigator.share) await navigator.share(data);
            else await navigator.clipboard?.writeText(window.location.href);
        });
        card.querySelector('[data-ticket-calendar]')?.addEventListener('click', () => {
            const start = toCalendarDate(card.dataset.calendarStart);
            const end = toCalendarDate(card.dataset.calendarEnd);
            const ics = `BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nDTSTART:${start}\nDTEND:${end}\nSUMMARY:${card.dataset.calendarTitle ?? 'Cinema ticket'}\nLOCATION:${card.dataset.calendarLocation ?? ''}\nEND:VEVENT\nEND:VCALENDAR`;
            const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' })); link.download = 'cinema-ticket.ics'; link.click(); URL.revokeObjectURL(link.href);
        });
    });
    if ('serviceWorker' in navigator && document.querySelector('[data-ticket-card]')) navigator.serviceWorker.register('/service-worker.js').catch(() => {});
};
