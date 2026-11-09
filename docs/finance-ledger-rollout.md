# Tish ERP accounting rollout and historical reconciliation

## Scope

This patch introduces double-entry posting for normal operating sources, optional
approved-salary accruals, supplier FX realized gains/losses, protected cash closes,
and a **read-only** historical ledger gap report. It must be validated against a
restored copy of the production database before deployment.

### Deployment gate — do not skip

1. Take a **verified MySQL backup** (database plus payment-proof storage) and
   restore it into an isolated environment. Never run tests that use
   `RefreshDatabase` against production.
2. Compare each branch's physical cash/bank and outstanding customer/supplier
   balances against the restored database and accounting journals.
3. On the restored copy, run migrations, then run:
   ```bash
   php artisan finance:audit-ledger --from=2026-01-01 --to=2026-11-09 --save
   php artisan test tests/Feature/Finance/AccountingBooksTest.php tests/Feature/Finance/DailyCashReconciliationTest.php
   php artisan test
   ```
4. Check the private audit JSON under `storage/app/private/finance-audits/`.
   Its **source IDs, dates, location, amount and period_status** are evidence for
   a human-led reconciliation, not a backfill instruction.
5. Review missing-cost snapshots. **Never fill unknown COGS with zero or a
   reconstructed price without purchase/consumption evidence.**
6. For a closed period, **do not reopen or rewrite** historical ledgers just
   to reconcile a gap. Document opening balances and use an authorized dated
   adjustment in an open period after accounting review.
7. Only after successful reconciliation and confirmed rollback plan should a
   trained operator deploy to production. Monitor ledger postings and cash
   variances by branch.

## Multi-currency policy

Amounts in the GL are expressed in the active base currency. Supplier invoices
record payable at invoice rate; payments settle the liability at its original
rate and post differences to realized FX loss `6200` or FX gain `4200`.
Physical cash in a foreign currency cannot be counted as base-currency banknotes.
This change **does not** implement per-currency cash drawers, automatic
bank-statement matching, or unrealized FX revaluation of open balances.

## Salary accounting

By default, legacy behavior recognizes salary expense on payment, not
approval. To opt in **only after old salary liabilities are reconciled**,
set system key `finance_payroll_accrual_enabled` to a boolean true
(e.g. via an authorized configuration procedure). When enabled, approving
new payroll periods posts debit `6100` and credit `2100` once per item.
Payments debit the accrued liability instead of recording duplicate expenses
and may recognize realized FX differences. Voiding a paid salary leaves cash
unchanged and creates a receivable `1160` when recovery is due.

Do not enable this for a historical approved payroll period retroactively.
Any salary payment that exceeds the accrued current-period amount requires
review of prior-period liabilities and wages. Disable accrual until verified.

## Historical data and reconciliation decisions

`php artisan finance:audit-ledger` is read-only. It deliberately has no
`--apply` or mass-import mode. A missing journal can reflect an unapproved
transaction, a closed-period policy, an incorrectly attributed branch,
a currency conversion issue or legacy import. Automatic journal creation
from current source status alone risks duplicate or incorrect balances.

Review each discrepancy with its original transaction, independent bank or
cash proof, counterparty statement, appropriate exchange rate and proper
period. Record any correction with an explicit unique source and audit trail.
Close a period only when the source gap count and treasury reconciliation
are supported by those records.

## Validation

The PR runs ERP CI, PDF regression and HR regression through GitHub Actions.
Successful SQLite CI does not prove compatibility with a populated
production MySQL database. Preserve the PR as draft until a restored
production-database run and approved accounting review are complete.


## MySQL-specific preflight

The new `.github/workflows/finance-mysql.yml` job uses MySQL 8 rather
than only the SQLite unit-test database. It first runs all 170 historical
migrations on a fresh database, then tests ledger, cash, payments and payroll
against MySQL. A historical menu migration formerly dropped an index still
required by an InnoDB product foreign key; the forward migration now installs
a replacement product-leading index first. Its rollback must **not** discard
duplicate-per-branch menu rows. 

For an existing production database, MySQL fresh-install tests cannot prove
that its live historical schema/data match; restore a real backup to staging,
inspect `php artisan migrate:status` and preview migrations before deployment.
Never run `php artisan migrate:fresh` or `php artisan test` against production.
