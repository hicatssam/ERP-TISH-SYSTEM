# TISH ERP — release candidate gate (21 November 2026)

**Status: NOT APPROVED FOR PRODUCTION.** Source review and test files do not prove the user's current WAMP checkout or database are deployable.

## Sources and branch reconciliation

- Main has a GitHub Actions MySQL workflow (`.github/workflows/finance-mysql.yml`) for migrations, POS race tests, finance, reconciliation, payroll and cashier workflows.
- The repository has many open draft PRs. Specifically: #118 branch stock requests, #119 demo procurement, #120 disbursement source accounts/schema visibility, #121 Breakfast starter catalogue, #123 incoming/bank-sales UX. Other finance/payment PRs (#32, #82, #86, #88, #89) may overlap. **Do not merge blindly.** Decide the exact included PRs; resolve conflicts and perform all testing against one committed release candidate SHA.
- User has copied individual files from draft PRs into local WAMP. The missing `is_customer_visible` column caused a 500 in `PaymentController::bankSales`; source and migrations MUST match before deployment.
- Local finance database evidence supplied by user: 8 bank/e-wallet sales, 6 confirmed (₪178), 1 pending (₪138), 1 rejected (₪119.76), 0 standalone incoming transfers, 1 supplier outgoing payment. 6 of the 8 bank sales lacked receiving-account IDs. Only one configured receiving account (B01 Bank of Palestine). No evidence of actual bank reconciliation yet.

## 1. Safe technical preflight — local WAMP

Backup both source and business MySQL database first. Use a **separate test database**, never the populated `tish_resturant_db`, for any test or destructive seeding. The provided PowerShell preflight checks `phpunit.xml` for `sqlite :memory:` and refuses dangerous overrides.

```powershell
powershell -ExecutionPolicy Bypass -File scripts/qa/tish-release-preflight.ps1
```

A nonzero exit blocks release. Logs are saved to `storage/app/qa/preflight-YYYYMMDD-HHMMSS.log`. It checks Git cleanliness and HEAD SHA, Composer platform, Laravel routes, migration status (read-only), **full PHPUnit suite**, and Vite production build. It does not attempt to merge, migrate, seed or deploy.

## 2. MySQL CI / coverage

The 162 tracked PHP test files across unit and feature tests are not a guarantee of all use cases. Run the GitHub `Tish Finance MySQL Integration` workflow on the **release candidate** PR. Verify migration success, financial GL, double posting prevention, refund/correction, supplier payments, store sale/receipt, stock expiry, interbranch transfers, payroll, POS concurrency, roles and cross-branch data isolation. Tests passing on SQLite cannot substitute for MySQL integration.

## 3. Manual smoke tests (performed on a staging copy of sanitized data)

| Flow | Expected result / release gate |
|---|---|
| Login, employee roles, multi-branch | Allowed users see only their branch; prohibited operations return 403 |
| User/branch configuration and business branding | Settings save without affecting unrelated branches; logos/icons work |
| POS cash and bank/e-wallet | Invoice, correct remaining balance, customer proof and payment status; no double charged settlement |
| Customer QR/mobile menu, cart, checkout | Branch, availability, taxes, options, logos, RTL layout, mobile responsiveness |
| Restaurant tables + KDS | Dine-in/takeaway, table session, kitchen station routing, cancellations and invoice consistency |
| Breakfast catalogue and other menu items | Six Breakfast items editable; no zero-price order or recipe stock depletion before real price/recipe set |
| Procurement + raw-material stock | PO → receipt → invoice → partial payment → return; batch, expiry, FEFO, accurate valuation |
| Branch requests/transfers | No factory dependency; branch-to-branch issues/receives preserve stock and audit |
| Fixed/variable expenses and investments | Category, multi-branch posting, no capital purchases wrongly expensed; approvals and reversal |
| Cash sessions, banks/wallets | Opening/closing, cash corrections, actual source/destination account, per-currency amounts |
| Incoming transfers + bank sales | Check pending vs confirmed, receiving account mapping, no duplicated receipts; account-specific reconciliation |
| Supplier outgoing transfers | Account source, beneficiary, method, reference, GL/AP debit and bank credit, no double posting |
| Payroll + employee reimbursements | Period close, paid personally vs company paid, accruals, permissions |
| Accounting books/GL | Debit=credit; matched reconciliation with sales, stock costs, supplier payables, payroll and returns |
| Reports and exports | PDF/Excel under permissions and filters; export sums match UI; Arabic text renders correctly |
| Finance audit, backups and logs | No lost/mutable journal entries; recoverable backup; permissions and private file delivery |
| Notifications, PWA, jobs, storage | Correct URLs, no broken jobs, private proofs protected, queues and schedules functional |

For every manual test record role, branch, expected vs actual, screenshot, PASS/FAIL and exact test document IDs.

## 4. Hard blockers already identified

- Inconsistent local migrations (`is_customer_visible` code/schema mismatch).
- No verified per-bank/wallet balance reconciliation; banking inflows and standalone incoming transfers are separate sources and not automatically matched.
- Historical bank sale payments without receiving accounts; don't guess account assignment or backfill.
- Draft PRs still unmerged, potentially overlapping financial/stock changes.
- No full-suite, MySQL workflow, production-like smoke and backup-restore evidence supplied yet.

## 5. Production rollout conditions

1. Freeze a single reviewed release SHA and write a release manifest of merged changes.
2. MySQL backup, verify restore **in an isolated environment** and record rollback plan.
3. Compare production schema with release migrations in staging; never `migrate:fresh` or full demo seed in a populated restaurant DB.
4. Set `APP_ENV=production`, `APP_DEBUG=false`; keep existing `APP_KEY` for an existing installation, protect `.env` and payment proofs, validate credentials, domains, HTTPS, mail, queue workers and scheduler.
5. Deploy code+assets together using `composer install --no-dev --prefer-dist --optimize-autoloader`, Vite build, maintenance window, controlled `php artisan migrate --force` only after staging success and verified backup, storage permissions, `php artisan optimize` and controlled workers restart.
6. Post-deploy run role/login/POS/bank sale/cash/stock/QR/KDS/read-only accounting smoke and monitor errors. Roll back code + DB appropriately if a financial invariant breaks.

**Release decision:** No production release until every hard blocker has documented evidence of resolution.
