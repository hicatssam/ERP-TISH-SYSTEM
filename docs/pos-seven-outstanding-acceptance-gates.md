# TISH POS: the seven remaining acceptance-test gaps

All results below must be interpreted against the current HEAD on this audit branch. **Adding a test is not a PASS until GitHub Actions executes it successfully.** Run tests only with isolated fixtures; never production orders, refunds, shifts or inventory.

| ID | User scenario | Automated evidence in this PR | What it proves | Still required before claiming full acceptance |
|---|---|---|---|---|
| GAP-01 | Browser loses connection after payment | `tests/Frontend/tish-pos-seven-gaps-regression.test.cjs` NET-01..03 + existing POS-04, POS-RACE-01 | Invalid/empty HTTP 2xx is treated as *ambiguous*; checkout token retained until valid `order_id`; duplicate POST does not create extra money/stock entries | Actual authenticated browser request interception: commit succeeds, response is dropped, refresh and retry |
| GAP-02 | Invoice printer stops / popup blocked | Frontend PRINT-01..03 | Missing window, missing print URL, or thrown preview navigation do not report a saved sale as failed; cashier gets reprint guidance | Actual receipt printer disconnected mid-print; confirm paper spool/reprint workflow and driver |
| GAP-03 | POS on mobile/tablet | Frontend RESPONSIVE-01 asserts responsive CSS breakpoints; existing CSS mobile rules | Only structural presence of responsive rules, **not** usability, fitting or touch accuracy | Playwright/real devices at 360x800, 390x844, 768x1024, 1024x768, 1366x768; screenshots, tap size, scroll and checkout visibility |
| GAP-04 | Ten customers + five kitchen orders | `PosCashierShiftSimulationTest::pos_49_...` | Ten isolated **sequential** checkouts with 5 KOT and 5 payments, corresponding invoices and stock | Actual parallel ten-client load test, simultaneous KDS state progression, latency and queue timing |
| GAP-05 | Cash + card payment | `PosCashierShiftSimulationTest::pos_50_...` | Finance service accepts two valid tenders on an existing unpaid order, reconciles invoice, rejects extra amount | POS's **single-method checkout** still has no multi-tender UX/API contract, so mixed tender cannot be called supported on one checkout |
| GAP-06 | Manager approval of sensitive discounts | Existing POS manual discount reason validation | Reason is required for a manual discount. **No manager approval workflow verified.** | Define business threshold and permissions, server-side one-time approval and audit trail; then add negative and positive tests |
| GAP-07 | Cashier edits exactly while KDS status changes | Existing POS-31/POS-32, `OrderService::updateOrder` order lock, `KitchenTicketService` transition locks | Post-dispatch edits to quantity/notes rejected under current state and order lock | Actual two-connection race of `start/markReady/serve` vs cashier edit; verify lock order, deadlock/retry and immutable invoice/inventory |

## Risks and release gates

- **P0:** Never mark an ambiguous checkout as paid/confirmed in the UI until a valid server `order_id` is received. Keep request token on retries. Never retry with a newly generated token automatically.
- **P1:** Do not claim a receipt *printed* based solely on a `window.open` or a print URL; actual printer confirmation requires hardware/driver integration.
- **P1:** Do not add mixed-tender buttons that produce multiple payments without server-side idempotent allocation, permissions and explicit UI design.
- **P1:** Manager discount approvals must be enforced server-side; hiding a button is not authorization.
- **P1:** KDS-vs-cashier edits should be tested concurrently on MySQL with two independent connections, not only sequential feature tests.
- **P2:** Run an actual touchscreen/viewport benchmark and measure clicks, modal reachability and scroll containment.

**No destructive migrations, production writes, or deployment. This branch is for QA and safe browser confirmation handling.**
