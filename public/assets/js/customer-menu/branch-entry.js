(() => {
    'use strict';
    const script = document.currentScript;
    const code = script?.dataset.branchCode;
    const chooser = script?.dataset.branchEntryUrl;
    if (!code || !chooser) return;
    const params = new URLSearchParams(window.location.search);
    // Explicit selection also allows the menu to work if browser storage is blocked.
    // Table QR links already identify their branch and must keep their table context.
    if (params.get('branch_selected') === '1' || params.has('table')) {
        try { localStorage.setItem('dahab_customer_branch', code); } catch (_) {}
        return;
    }
    let selected = null;
    try { selected = localStorage.getItem('dahab_customer_branch'); } catch (_) {}
    if (!selected) {
        window.location.replace(chooser);
    } else if (params.get('source') === 'pwa' && selected !== code) {
        // Older installed apps may still launch their original branch URL.
        const entry = new URL(chooser, window.location.href);
        entry.searchParams.delete('choose');
        window.location.replace(entry.href);
    }
})();
