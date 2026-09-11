import { initModals, initPasswordControls, initTheme, initToasts } from './modules/common.js';
import { initAdminShell } from './modules/admin-shell.js';
import { initLanguageMenus } from './modules/language-menus.js';
import { initUserSelection } from './modules/user-selection.js';
import { initUserShell } from './modules/user-shell.js';
import { initNotificationBells } from './modules/notification-bell.js';
import { initUserMenus } from './modules/user-menu.js';

initTheme();
initPasswordControls();
initLanguageMenus();
initAdminShell();
initUserShell();
initModals();
initUserSelection();
initToasts();
initNotificationBells();
initUserMenus();
document.querySelector('[data-error-summary]')?.focus();
if (document.querySelector('[data-seat-picker]')) {
    import('./modules/seat-picker.js').then(({ initSeatPickers }) => initSeatPickers());
}

if (document.querySelector('[data-booking-checkout]')) {
    import('./modules/booking.js').then(({ initBookingCheckout, initComboTotals }) => {
        initBookingCheckout();
        initComboTotals();
    });
}

if (document.querySelector('[data-payment-status]')) {
    import('./modules/payment-status.js').then(({ initPaymentStatus }) => initPaymentStatus());
}

if (document.querySelector('[data-ticket-card]')) {
    import('./modules/ticket.js').then(({ initTicketActions }) => initTicketActions());
}
