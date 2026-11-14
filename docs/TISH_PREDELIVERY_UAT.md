# TISH ERP — Pre-delivery acceptance and integration test matrix

This is the controlled pre-launch test plan for **one operating branch B01**, **one HALL**, **KITCHEN + PREP**, **dine-in + takeaway**. It is not evidence that a scenario has passed. Record actual evidence for each case before sign-off.

## Database safety gate

- Use a **dedicated, disposable MySQL test database** for automated tests using `RefreshDatabase`. Confirm its name differs from `tish_resturant_db`. **Never** point test commands, `migrate:fresh`, or destructive cleanup to the retained UAT/live database.
- Back up current `tish_resturant_db` before UAT setup changes or reseeding.
- Historical B02/FAC orders, invoices, stock movements and kitchen tickets must not be automatically moved or deleted.
- The existing database seeder is `php artisan db:seed --class=TishExistingDatabaseSeeder`, **not** `DatabaseSeeder`; it does not insert invented physical opening stock. Verify each recipe before approval for actual service.
- The cashier PWA is an installable *online* entry point into existing POS; it must **never** pretend sales have synced while offline.

## P0 — Cross-module acceptance scenarios

| ID | Flow | Evidence / expected result | Status |
|---|---|---|---|
| POS-01 | Cashier login & independent app launch | Installed TISH POS shortcut opens authorized `/restaurant/pos`; anonymous users redirected to login; unauthorized users denied | Pending UAT |
| POS-02 | Cashier branch boundary | B01 cashier cannot use B02 payment methods or edit other-branch tickets; dormant B02 and FAC not selectable | Pending UAT |
| POS-03 | Menu and recipes | Active products listed in POS have branch menu entries and approved, positive-quantity recipes for raw materials | Pending UAT |
| POS-04 | Takeaway / cash | Create one takeaway order, pay now, print invoice; exactly one order/invoice/collection chain, no duplicate retry | Pending UAT |
| POS-05 | Dine-in / tables | Open HALL table, create order, keep session linked, serve and complete, close table | Pending UAT |
| POS-06 | Modifier + variants + discount | Price changes correctly; all selected recipe/raw consumption and payment totals match receipt | Pending UAT |
| POS-07 | Partial/unverified payment | Verified vs pending payment have appropriate invoice/confirmation treatment; cannot bypass validation | Pending UAT |
| POS-08 | Duplicate submission | Idempotency prevents duplicate order, invoice, receipt and inventory consumption on retries | Pending UAT |
| KDS-01 | Station routing | Burgers/fries/appetizers → KITCHEN; sauces/drinks/shakes → PREP; tickets enter correct queue | Pending UAT |
| KDS-02 | State changes | queued → preparing → ready → served; order cannot complete prematurely if rule enabled | Pending UAT |
| KDS-03 | Historical tickets | Older GRILL/FRYER/BAR tickets remain addressable and do not vanish when new stations are used | Pending UAT |
| INV-01 | Confirmation stock deduction | For 2 Classic Beef Burgers, precisely 2× approved ingredient units deducted at B01, and immutable movements recorded | Pending UAT |
| INV-02 | Insufficient stock | Reject sale confirmation atomically; other ingredient quantities and invoice unchanged | Pending UAT |
| INV-03 | Cancellation | Unpaid confirmed order reverses stock once, marks financial reversal correctly; repeated cancellation safe | Pending UAT |
| INV-04 | Threshold notification | A raw ingredient crossing branch minimum emits LowStockDetected and creates visible notification for intended role; no unrelated branch alert | Pending UAT |
| INV-05 | Units + stock reconciliation | kg/liter/piece label matches Product.unit; inventory quantity equals journaled movement balance and reservations | Pending UAT |
| BUY-01 | Purchase → receipt | Approved receipt increases raw stock once with correct unit conversion, batch/expiry when applicable, cost and supplier document | Pending UAT |
| BUY-02 | Supplier invoice & payment | Payable, supplier balance, treasury and journal reconcile; retry and cancellation do not duplicate entries | Pending UAT |
| FIN-01 | Sale accounting | Invoice, payment, stock consumption, COGS snapshot, journal and profitability agree for same order | Pending UAT |
| FIN-02 | Refund | Refund/void produces appropriate reversals without double refund or double stock return | Pending UAT |
| FIN-03 | End-of-shift cash | Opening cash + receipts - expenses/refunds reconciles; discrepancy visible with actor/time and control over closure | Pending UAT |
| FIN-04 | Period close | Prevent closing with unlinked operating entries or blocking open cash sessions; no retroactive change to closed period | Pending UAT |
| FIN-05 | Reports | B01 operational report does not silently aggregate historical B02/FAC; historical report explicitly scoped | Pending UAT |
| FIN-06 | Profit data quality | If missing actual costs, mark profit estimate incomplete; do not silently present as verified profit | Pending UAT |
| AUTH-01 | Roles | Cashier only has POS-required capabilities; kitchen can't alter finance; accountant cannot alter stock without permission | Pending UAT |
| OPS-01 | Backup / restore | Test restore on isolated database and signed-off clean handover seed without synthetic orders | Pending UAT |
| OPS-02 | Performance | Fast product browsing, keyboard/scanner input, repeat order, printing and device reconnect at actual counter hardware | Pending UAT |

## Existing automated coverage to run first

- `tests/Feature/TishRecipeStockFlowTest.php` — recipe linkage, confirm/cancel, insufficient stock, re-seed safety, stock event.
- `tests/Feature/Restaurant/RestaurantPosCatalogTest.php` — branch/product catalog, payment-method isolation.
- `tests/Feature/Restaurant/RestaurantPosNonCashDetailsValidationTest.php` — non-cash validation.
- `tests/Feature/Finance/` — accounting, daily cash, transfers and payment flows.
- `tests/Feature/Inventory/`, `tests/Feature/CustomerOrdering/`, `tests/Feature/Restaurant/` — additional inventory and customer flows.
- CI workflows: ERP CI and Tish Finance MySQL Integration. **Passing CI does not replace actual UAT on TISH's hardware and branch configuration.**

## Evidence template for every manual UAT case

Record: **case ID / tester / date / Git commit / test DB / initial stock and money / actions / order number / invoice number / relevant movement IDs / final state / pass or fail / screenshot or log / corrective PR**.

## Exit criteria

1. All P0 flows passed against isolated UAT data, including negative cases and retries.
2. No unresolved negative stock, duplicate financial movements, or missing accounting postings.
3. Installation and launch tested on the actual cashier computer; network outage does not record fake successful payments.
4. Physical opening stock and menu/recipe quantities checked with restaurant operator.
5. Fresh handover database contains approved catalogue, permissions and settings, without demo orders or fake financial balances; backup and recovery exercise documented.
