/* Load the shared date formatter for any secondary layout that only includes app.js. */
(() => {
    if (
        window.DahabDateFormatter
        || document.querySelector(
            'script[data-dahab-date-loader]'
        )
    ) {
        return;
    }

    const appScript = Array.from(
        document.scripts
    ).find(script =>
        script.src.includes(
            '/assets/js/app.js'
        )
    );

    const version = (() => {
        try {
            return appScript
                ? new URL(appScript.src)
                    .searchParams
                    .get('v')
                : null;
        } catch (_) {
            return null;
        }
    })();

    const script =
        document.createElement('script');

    script.dataset.dahabDateLoader = '1';
    script.src =
        '/assets/js/date-format.js'
        + (
            version
                ? '?v='
                    + encodeURIComponent(version)
                : ''
        );

    document.head.appendChild(script);
})();

/* ═══════════════════════════════════════════════
   DAHAB SWEETS — App JavaScript v2
   ═══════════════════════════════════════════════ */

// ── Sidebar + Overlay ───────────────────────────
const sidebar       = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const overlay       = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden'; // prevent scroll behind overlay on mobile
}

function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
}

overlay?.addEventListener('click', closeSidebar);

// Close sidebar on resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) closeSidebar();
});

// ── Notification Dropdown ───────────────────────
const notifBtn      = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');

// ── Notification type → icon SVG map ────────────
const _notifIcons = {
    low_stock_detected: `<svg viewBox="0 0 24 24" fill="none" stroke="#B8892E" stroke-width="2" width="16" height="16"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    order_created: `<svg viewBox="0 0 24 24" fill="none" stroke="#16283A" stroke-width="2" width="16" height="16"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`,
    order_status_changed: `<svg viewBox="0 0 24 24" fill="none" stroke="#16283A" stroke-width="2" width="16" height="16"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>`,
    payment_received: `<svg viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2" width="16" height="16"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>`,
    special_cake_order_transitioned: `<svg viewBox="0 0 24 24" fill="none" stroke="#B8892E" stroke-width="2" width="16" height="16"><path d="M20 11.08V8l-6-6H6a2 2 0 0 0-2 2v16c0 1.1.9 2 2 2h6"/><path d="M14 3v5h5M18 21v-6M15 18h6"/></svg>`,
};

function _renderNotifItems(items) {
    const list = document.getElementById('notifList');
    if (!list) return;
    if (!items || items.length === 0) {
        list.innerHTML = '<div class="notif-empty">لا توجد إشعارات جديدة</div>';
        return;
    }
    list.innerHTML = items.map(n => {
        const icon   = _notifIcons[n.type] || _notifIcons['order_created'];
        const href   = n.url ? n.url : '#';
        return `<a class="notif-item" href="${href}" data-id="${n.id}">
            <span class="notif-item-icon">${icon}</span>
            <span class="notif-item-body">
                <span class="notif-item-msg">${n.message}</span>
                <span class="notif-item-time">${n.time}</span>
            </span>
        </a>`;
    }).join('');

    // Mark individual as read on click
    list.querySelectorAll('.notif-item[data-id]').forEach(el => {
        el.addEventListener('click', async () => {
            const id   = el.dataset.id;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            try { await fetch(`/notifications/${id}/read`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }); } catch {}
        });
    });
}

async function _loadRecentNotifs() {
    try {
        const res  = await fetch('/notifications/recent', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        _renderNotifItems(data.items || []);
    } catch {
        const list = document.getElementById('notifList');
        if (list) list.innerHTML = '<div class="notif-empty">تعذّر تحميل الإشعارات</div>';
    }
}

let _notifLoaded = false;
if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const wasOpen = notifDropdown.classList.contains('open');
        notifDropdown.classList.toggle('open');
        userDropdown?.classList.remove('open');
        // Load on first open, or every time it opens
        if (!wasOpen) {
            _loadRecentNotifs();
        }
    });
}

// ── User Dropdown ───────────────────────────────
const userMenuBtn  = document.getElementById('userMenuBtn');
const userDropdown = document.getElementById('userDropdown');

if (userMenuBtn && userDropdown) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('open');
        notifDropdown?.classList.remove('open');
    });
}

// Close all dropdowns on outside click
document.addEventListener('click', () => {
    notifDropdown?.classList.remove('open');
    userDropdown?.classList.remove('open');
});

// ── Mark All Notifications Read ─────────────────
const markAllRead = document.getElementById('markAllRead');
if (markAllRead) {
    markAllRead.addEventListener('click', async (e) => {
        e.preventDefault();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        try {
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            const badge = document.getElementById('notifCount');
            if (badge) { badge.style.display = 'none'; badge.textContent = '0'; }
            const list = document.getElementById('notifList');
            if (list) list.innerHTML = '<div class="notif-empty">لا توجد إشعارات جديدة</div>';
        } catch {}
    });
}

// ── Notification Sound (Web Audio API — no file needed) ─────
let _notifAudioCtx = null;

function playNotificationSound() {
    try {
        if (!_notifAudioCtx) {
            _notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        const ctx = _notifAudioCtx;

        // Two-note soft chime: high C then E
        const notes = [
            { freq: 1046.5, startAt: 0.00, duration: 0.18 },   // C6
            { freq: 1318.5, startAt: 0.16, duration: 0.22 },   // E6
        ];

        notes.forEach(({ freq, startAt, duration }) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type      = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime + startAt);

            gain.gain.setValueAtTime(0,    ctx.currentTime + startAt);
            gain.gain.linearRampToValueAtTime(0.22, ctx.currentTime + startAt + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + startAt + duration);

            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime + startAt);
            osc.stop(ctx.currentTime  + startAt + duration + 0.05);
        });
    } catch (_) {}
}

// ── Fetch Unread Notification Count ────────────
let _prevNotifCount = null;

async function refreshNotifCount() {
    try {
        const res = await fetch('/notifications/unread-count', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        const badge = document.getElementById('notifCount');
        if (badge) {
            badge.textContent = data.count;
            badge.style.display = data.count > 0 ? 'flex' : 'none';
        }
        // Play sound when new notifications arrive (count increased since last poll)
        if (_prevNotifCount !== null && data.count > _prevNotifCount) {
            playNotificationSound();
        }
        _prevNotifCount = data.count;
    } catch {}
}

if (document.getElementById('notifCount')) {
    refreshNotifCount();
    setInterval(refreshNotifCount, 30000);
}

// ── Auto-dismiss Toasts ─────────────────────────
document.querySelectorAll('[data-auto-dismiss]').forEach(toast => {
    setTimeout(() => toast.remove(), 5000);
});

// ── Unified Confirmation Modal ───────────────────
let _confirmAction = null;
let _confirmTrigger = null;

function actionMetaFromElement(element, form = null) {
    const explicitType =
        element?.dataset?.confirmType
        || form?.dataset?.confirmType
        || '';

    const explicitTitle =
        element?.dataset?.confirmTitle
        || form?.dataset?.confirmTitle
        || '';

    const explicitText =
        element?.dataset?.confirmText
        || form?.dataset?.confirmText
        || '';

    const explicitMessage =
        element?.dataset?.confirm
        || form?.dataset?.confirm
        || '';

    const buttonText = String(
        element?.textContent
        || element?.value
        || ''
    ).trim();

    const actionUrl = String(
        form?.getAttribute('action')
        || element?.getAttribute?.('href')
        || ''
    ).toLowerCase();

    const methodOverride = String(
        form?.querySelector('input[name="_method"]')?.value
        || ''
    ).toUpperCase();

    const source = (buttonText + ' ' + actionUrl).toLowerCase();

    const isDelete =
        methodOverride === 'DELETE'
        || /\b(delete|destroy|remove)\b/.test(source)
        || /(حذف|إزالة)/.test(source);

    const isReject =
        /\b(reject|decline)\b/.test(source)
        || /(رفض)/.test(source);

    const isCancel =
        /\b(cancel)\b/.test(source)
        || /(إلغاء)/.test(source);

    const isSuccess =
        /\b(approve|approved|confirm|complete|receive|received|accept|activate|dispatch)\b/.test(source)
        || /(موافقة|اعتماد|تأكيد|إكمال|استلام|قبول|تفعيل|إرسال)/.test(source);

    let type = explicitType;

    if (!type) {
        if (isDelete || isReject) type = 'danger';
        else if (isCancel) type = 'warning';
        else if (isSuccess) type = 'success';
        else type = 'info';
    }

    let title = explicitTitle;
    let confirmText = explicitText;

    if (!title) {
        if (isDelete) title = 'تأكيد الحذف';
        else if (isReject) title = 'تأكيد الرفض';
        else if (isCancel) title = 'تأكيد الإلغاء';
        else if (isSuccess) title = 'تأكيد العملية';
        else title = 'تأكيد الإجراء';
    }

    if (!confirmText) {
        if (isDelete) confirmText = 'حذف';
        else if (isReject) confirmText = 'رفض';
        else if (isCancel) confirmText = 'تأكيد الإلغاء';
        else if (isSuccess) confirmText = buttonText || 'تأكيد';
        else confirmText = 'متابعة';
    }

    let body = explicitMessage;

    if (!body) {
        if (isDelete) {
            body = 'سيتم حذف هذا العنصر من النظام. السجلات التاريخية المرتبطة به تبقى محفوظة متى كان النظام يدعم ذلك.';
        } else if (isReject) {
            body = 'سيتم رفض هذا الطلب أو السجل. تأكد من القرار قبل المتابعة.';
        } else if (isCancel) {
            body = 'سيتم إلغاء العملية الحالية. هل تريد المتابعة؟';
        } else if (isSuccess) {
            body = 'سيتم تنفيذ هذا الإجراء وتحديث حالة السجل في النظام.';
        } else {
            body = 'هل أنت متأكد من تنفيذ هذه العملية؟';
        }
    }

    return {
        type,
        title,
        body,
        confirmText
    };
}

// Receipt details are calculated from the form's currently entered values.
// Other confirmation dialogs stay unchanged.
function updateReceiptConfirmationSummary(form) {
    const panel = document.getElementById('confirmReceiptSummary');
    if (!panel) return;

    const isReceipt = form?.dataset?.confirmReceipt === '1';
    panel.hidden = !isReceipt;
    if (!isReceipt) return;

    const inputs = Array.from(
        form.querySelectorAll('input[name$="[received_quantity]"]')
    );

    const differences = inputs.filter(input => {
        const sent = Number(input.max);
        const received = Number(input.value);

        return Number.isFinite(sent)
            && Number.isFinite(received)
            && Math.abs(sent - received) > 0.0005;
    }).length;

    const invoice = document.getElementById('confirmReceiptInvoiceNumber');
    const count = document.getElementById('confirmReceiptItemsCount');
    const differenceCount = document.getElementById('confirmReceiptDifferencesCount');
    const hint = document.getElementById('confirmReceiptHint');

    if (invoice) invoice.textContent = form.dataset.confirmInvoiceNumber || '—';
    if (count) count.textContent = String(inputs.length);
    if (differenceCount) differenceCount.textContent = String(differences);
    panel.classList.toggle('has-differences', differences > 0);

    if (hint) {
        hint.textContent = differences > 0
            ? 'يوجد فرق بين المرسل والمستلم في ' + differences
                + ' صنف. ستُثبت الكميات والفروقات في الفاتورة.'
            : 'الكميات المسجلة مطابقة للمرسلة. سيتم توثيق الاستلام وإصدار الفاتورة.';
    }
}

function openConfirmModal({
    title,
    body,
    confirmText,
    type = 'info',
    onConfirm,
    trigger = null,
    form = null
}) {
    const modal = document.getElementById('confirmModal');
    if (!modal) {
        if (typeof onConfirm === 'function') onConfirm();
        return;
    }

    const icon = document.getElementById('confirmIcon');
    const btn = document.getElementById('confirmBtn');

    document.getElementById('confirmTitle').textContent =
        title || 'تأكيد العملية';

    document.getElementById('confirmBody').textContent =
        body || 'هل أنت متأكد؟';

    document.getElementById('confirmEyebrow').textContent =
        type === 'danger'
            ? 'إجراء حساس'
            : type === 'success'
                ? 'تأكيد واعتماد'
                : type === 'warning'
                    ? 'تنبيه قبل المتابعة'
                    : 'تأكيد الإجراء';

    icon?.classList.remove(
        'modal-icon-danger',
        'is-danger',
        'is-success',
        'is-warning',
        'is-info'
    );
    icon?.classList.add('is-' + type);

    btn.className = 'btn '
        + (type === 'danger'
            ? 'btn-danger'
            : type === 'success'
                ? 'btn-success'
                : type === 'warning'
                    ? 'btn-gold'
                    : 'btn-gold');

    btn.textContent = confirmText || 'تأكيد';

    updateReceiptConfirmationSummary(form);

    _confirmAction = onConfirm;
    _confirmTrigger = trigger || document.activeElement;

    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('confirm-modal-open');

    window.setTimeout(() => btn.focus(), 30);
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (!modal) return;

    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('confirm-modal-open');

    const restoreFocus = _confirmTrigger;
    _confirmTrigger = null;
    _confirmAction = null;

    if (
        restoreFocus
        && typeof restoreFocus.focus === 'function'
    ) {
        window.setTimeout(() => restoreFocus.focus(), 20);
    }
}

document.getElementById('confirmBtn')?.addEventListener(
    'click',
    function () {
        const action = _confirmAction;
        _confirmAction = null;

        const modal = document.getElementById('confirmModal');
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('confirm-modal-open');

        if (typeof action === 'function') {
            action();
        }
    }
);

document.getElementById('confirmCancelBtn')?.addEventListener(
    'click',
    closeConfirmModal
);

document.getElementById('confirmCloseBtn')?.addEventListener(
    'click',
    closeConfirmModal
);

document.getElementById('confirmModal')?.addEventListener(
    'click',
    (event) => {
        if (event.target === event.currentTarget) {
            closeConfirmModal();
        }
    }
);

document.addEventListener('keydown', (event) => {
    const modal = document.getElementById('confirmModal');

    if (
        event.key === 'Escape'
        && modal
        && modal.style.display !== 'none'
    ) {
        event.preventDefault();
        closeConfirmModal();
    }
});

/*
 * Central form protection.
 * This catches DELETE forms automatically and also sensitive
 * approval/rejection/cancellation/receive/complete actions.
 */
document.addEventListener(
    'submit',
    function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.confirmBypass === '1') return;
        if (form.dataset.confirmedOnce === '1') {
            form.dataset.confirmedOnce = '0';
            return;
        }

        const submitter = event.submitter || null;
        const methodOverride = String(
            form.querySelector('input[name="_method"]')?.value || ''
        ).toUpperCase();

        const actionUrl = String(
            form.getAttribute('action') || ''
        ).toLowerCase();

        const buttonText = String(
            submitter?.textContent || submitter?.value || ''
        ).trim();

        const source = (buttonText + ' ' + actionUrl).toLowerCase();

        const explicitConfirm =
            submitter?.dataset?.confirm
            || form.dataset.confirm;

        const isSensitive =
            Boolean(explicitConfirm)
            || methodOverride === 'DELETE'
            || /\b(delete|destroy|remove|approve|confirm|complete|receive|reject|cancel|accept|activate|dispatch)\b/.test(source)
            || /(حذف|إزالة|موافقة|اعتماد|تأكيد|إكمال|استلام|رفض|إلغاء|قبول|تفعيل|إرسال)/.test(source);

        if (!isSensitive) return;

        event.preventDefault();

        const meta = actionMetaFromElement(
            submitter,
            form
        );

        openConfirmModal({
            ...meta,
            trigger: submitter,
            form,
            onConfirm: () => {
                form.dataset.confirmedOnce = '1';

                if (
                    submitter
                    && typeof form.requestSubmit === 'function'
                ) {
                    form.requestSubmit(submitter);
                } else {
                    form.submit();
                }
            }
        });
    },
    true
);

/*
 * Confirm links and standalone action buttons.
 */
document.addEventListener(
    'click',
    function (event) {
        const element = event.target.closest(
            'a[data-confirm], button[data-confirm]'
        );

        if (!element) return;

        const form = element.closest('form');

        // Forms are handled by the submit listener above.
        if (form) return;

        event.preventDefault();

        const meta = actionMetaFromElement(
            element,
            null
        );

        openConfirmModal({
            ...meta,
            trigger: element,
            onConfirm: () => {
                const href = element.getAttribute('href');

                if (href) {
                    window.location.href = href;
                } else {
                    element.dispatchEvent(
                        new CustomEvent(
                            'dahab:confirmed',
                            { bubbles: true }
                        )
                    );
                }
            }
        });
    },
    true
);

// ── CSRF helper ─────────────────────────────────
function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

// ── POST via fetch ──────────────────────────────
async function postAction(url, data = {}) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    });
    return res.json();
}

// ── Client-side table search ────────────────────
document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.dataset.searchTable;
    const table = document.getElementById(tableId);
    if (!table) return;
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});

// ── Show inline toast ──────────────────────────
function showToast(message, type = 'success') {
    const container = document.getElementById('flashContainer');
    if (!container) return;
    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>'
    };
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `${icons[type] || icons.success}<span>${message}</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
