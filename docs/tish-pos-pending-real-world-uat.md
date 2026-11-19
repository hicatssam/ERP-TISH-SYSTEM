# TISH cashier — pending real-world UAT checklist

> **Scope:** Perform on a disposable staging database / sandbox, never against live sales or payment terminals.
> CI exercises code and isolated MySQL transactions; it **cannot prove physical printing, touch accuracy or a real browser network drop after commit**.

## A. Browser disconnected after the server commits checkout (P0)

1. Open a test cashier shift with a test menu product and exact inventory count.
2. In a browser with network interception (Playwright, DevTools proxy or local test gateway), submit one cashier checkout. Allow the HTTP POST to reach Laravel and commit the order, but drop **only the HTTP response**, not the request.
3. Observe the **ambiguous confirmation warning**, not a green sale-success message. The same user's draft should be visible (if storage permitted).
4. Use a separate browser session to verify there is exactly **one** order, invoice, payment and stock movement.
5. Restore connectivity. Before retrying, check server order history. Do **not** place a second checkout when the order already exists.
6. Repeat with the POST prevented from reaching the server at all, then retry the **unchanged** checkout with its original request token while the same browser tab session survives. Confirm only one order on the retry.
7. Repeat after a full browser shutdown, where sessionStorage might be gone. **Do not assume automatic idempotency across sessions.** Require history verification before recreating an order.

**Pass:** no double charge/stock, no false success, warning prominent, and one settled invoice after allowed retry.

## B. Physical receipt-printer interruption (P1)

1. Test on the actual configured thermal printer and workstation.
2. Commit a test invoice and deliberately disconnect printer / simulate paper-out **after server confirmation**.
3. Reconnect, open the saved order and reprint *without rerunning checkout*.
4. Check invoice number, amounts, refund/discount lines and reprint indication; compare cash and journal totals.
5. Cover blocked popup, browser print dialog cancellation, paper cut and driver timeout separately.

**Pass:** committed order remains paid once; only printing is retried. A preview URL alone is **not** evidence of paper output.

## C. Mobile and tablet touch UAT (P1)

Test viewport sizes 360×800, 390×844, 768×1024, 1024×768, 1366×768 with browser zoom 100% and keyboard visible. Verify product selection, variant/modifier dialog, discount reason, split tender, cash/card confirmation, checkout error messages, cancel buttons, and reprint links. Inspect physical touchscreen targets and horizontal overflow.

**Pass:** no hidden submit buttons, overlapping dialogs, accidental double tap, inaccessible fields or forced horizontal scrolling. Automated CSS checks alone are insufficient.

## D. Shared-terminal cashier change and power recovery (P1)

1. Sign in as cashier A; save an unpaid local draft, noting its items and total.
2. Sign out, sign in as cashier B **on the same browser and branch**. Verify cashier A's draft is absent.
3. Sign back in as cashier A. The user-scoped draft should be available to restore, subject to live product availability.
4. Refresh/restart the browser with an unsent draft: restore items and confirm updated availability/price against the server; do not trust old total as settled.
5. Simulate power loss while **payment is pending**. On reboot, first inspect server order history. Never assume an unknown checkout was not committed.
6. Repeat with localStorage disabled/full. Verify the POS shows a truthful error and staff can document the pending sale manually.

**Pass:** no draft leaks between cashiers, no duplicate payment, no silent promise of successful save when storage is blocked.

## E. Two-person manager discount approval (not implemented)

The current server policy is **manager-owned checkout** above configured discount thresholds. A separate one-use code / dual-person approval request is still **NOT implemented**. Do not instruct cashiers to borrow manager passwords; policy requirements (threshold, expiry, manager scope, single-use audit) must be approved before creating a delegated approval feature.

## F. Shift reconciliation and cancellation scenarios (needs more UAT)

Run one whole test shift: open float → cash sale → split sale → partial refund → cancelled unpaid kitchen ticket → manager-only discount → close with a deliberate cash shortage → compare drawer, invoice, ledger and stock. Then retry close/open actions and check no duplicate shift.

**Report for each row:** device, OS/browser, exact time, order/shift IDs in sandbox, PASS/FAIL, actual/expected, screenshot/log (no sensitive card/customer details).
