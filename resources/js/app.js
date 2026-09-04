import { initModals, initPasswordControls, initTheme, initToasts } from './modules/common.js';
import { initAdminShell } from './modules/admin-shell.js';
import { initLanguageMenus } from './modules/language-menus.js';
import { initUserSelection } from './modules/user-selection.js';

initTheme();
initPasswordControls();
initLanguageMenus();
initAdminShell();
initModals();
initUserSelection();
initToasts();
