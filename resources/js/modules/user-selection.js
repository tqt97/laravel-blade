export const initUserSelection = () => {
    const inputs = [...document.querySelectorAll('[data-user-selection]')];
    const selectAll = document.querySelector('[data-user-select-all]');
    const bulkActions = document.querySelector('[data-bulk-actions]');
    const bulkActionsTrigger = document.querySelector('[data-bulk-actions-trigger]');
    const bulkActionsMenu = document.querySelector('[data-bulk-actions-menu]');
    if (!inputs.length && !selectAll) return;
    const selectableInputs = inputs.filter((input) => !input.disabled);
    const sync = () => {
        const count = selectableInputs.filter((input) => input.checked).length;
        bulkActions?.classList.toggle('hidden', count === 0);
        if (count === 0 && bulkActionsMenu) {
            bulkActionsMenu.hidden = true;
            bulkActionsTrigger?.setAttribute('aria-expanded', 'false');
        }
        if (selectAll) {
            selectAll.checked = selectableInputs.length > 0 && count === selectableInputs.length;
            selectAll.indeterminate = count > 0 && count < selectableInputs.length;
        }
    };
    selectAll?.addEventListener('change', () => { selectableInputs.forEach((input) => { input.checked = selectAll.checked; }); sync(); });
    inputs.forEach((input) => input.addEventListener('change', sync));
    bulkActionsTrigger?.addEventListener('click', () => {
        if (!bulkActionsMenu) return;
        const open = !bulkActionsMenu.hidden;
        bulkActionsMenu.hidden = open;
        bulkActionsTrigger.setAttribute('aria-expanded', String(!open));
        bulkActionsTrigger.querySelector('[data-bulk-actions-chevron]')?.classList.toggle('rotate-180', !open);
    });
    document.addEventListener('click', (event) => {
        if (bulkActions && !bulkActions.contains(event.target) && bulkActionsMenu) {
            bulkActionsMenu.hidden = true;
            bulkActionsTrigger?.setAttribute('aria-expanded', 'false');
            bulkActionsTrigger?.querySelector('[data-bulk-actions-chevron]')?.classList.remove('rotate-180');
        }
    });
    sync();
};
