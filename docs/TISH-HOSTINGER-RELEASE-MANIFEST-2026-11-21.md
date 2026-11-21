# TISH / Hostinger — Release candidate inventory (2026-11-21)

**Deployment status: BLOCKED.** This is an audit record, not a signal to deploy or run a business database migration.

## Stable source baseline

- GitHub repository: `hicatssam/ERP-TISH-SYSTEM`
- `main` baseline commit: `d1d8bc08b981939a6effd7541f5854610644f52f` (2026-11-20)
- Candidate branch: `release/tish-hostinger-prep-20261121`
- VPS: Ubuntu 26.04.1 LTS, 2 vCPU, 7.7 GiB RAM, 96 GB disk, Nginx 1.28.3. PHP/DB/Composer not yet configured, so **no production services or customer data are deployed**.
- Production app expects PHP 8.4 per existing local WAMP stack and README/lockfile; Ubuntu 26.04 default PHP is not assumed compatible. Check platform requirements with actual target PHP before installation.

## Audit of GitHub state

- 172 migration files on `main` and 162 tracked PHP test files (Unit + Feature). This is file coverage, not a test-pass claim.
- PRs to investigate, in integration order:
  1. **#118** stock request without factory dependency — operational code; isolated tests required.
  2. **#123** incoming bank transfers vs bank sales — UI and dashboard; WAMP 9 tests / 35 assertions passed on 2026-11-21.
  3. **#120** outgoing supplier payment source account — migration + payment and public account visibility code. The WAMP DB needed `2026_11_21_180000_add_source_account_to_supplier_payments` and applied it successfully, but PR **MySQL CI failed** in `AccountingBooksTest::test_supplier_payment_service_uses_current_fx_for_invoice_currency_and_posts_realized_loss`: new disbursement-source validation blocks an old test fixture without an authorized source account. Do not relax business validation just to pass the test; update the fixture, then rerun MySQL tests.
  4. **#121** six Breakfast starter items, no invented prices/recipes; does NOT make items publicly orderable until configured.
  5. **#124** full WAMP preflight script/doc; does not approve deployment.
  6. **#119** demo operations/seeders: **NOT FOR A POPULATED PRODUCTION DATABASE**; decide separately for disposable staging only.
- Other unresolved open draft PRs: **#32** accounting/FX, **#82** expense behavior, **#86** posting integrity, **#87** POS customer, **#88** invoice settlement, **#89** internal bank proof. These require separate scope and careful merge review; not implicitly included in this release.
- Git conflict/semantic overlap hotspots: `PaymentController.php` (#32/#120), `PaymentService.php` (#32/#88/#89), `SupplierPaymentService.php` (#32/#120), `AccountingBookService.php` (#32/#86), `ExpenseWorkflowService.php` (#32/#82), `OrderController.php` (#88/#120), and `AccountingBooksTest.php` (#32/#86/#88/#89).
- The previous ERP CI stopped in **Node frontend test**: `tests/Frontend/customer-menu-order-flow.test.cjs`, QR table test, ReferenceError `lockedTableId is not defined`. The production checkout already uses a sessionStorage table-lock helper, so this release branch updates the test harness to include the lock helper and storage mock. CI must still confirm the correction.
- PDF layout CI failure exists on some financial branches and needs separate diagnosis.

## Baseline deploy hazards

- `.env.example` still contains defaults from Dahab: `APP_NAME="Dahab Sweets"`, `DB_DATABASE=dahab_sweets_demo`, `CACHE_PREFIX=dahab_`, `MAIL_FROM_NAME="Dahab Sweets"`. These are samples, **not safe TISH production values**. Create a private, server-specific `.env` with TISH branding and database, never commit it.
- `.gitignore` correctly excludes `.env`, `vendor`, `node_modules`, built Vite assets and public storage link, so a bare `git clone` **cannot** run production unaided. Build assets and install Composer dependencies.
- Nginx document root must be `<app>/public`; never expose project root, `.env` or proofs in `storage/app/private`.
- Public product images and user-uploaded records are NOT guaranteed to be in Git; migrate `storage/app/public` and any application-specific private upload directories deliberately with ownership and backup verification.
- Queue workers, scheduler, WebSocket/push if used, cron, database SQL mode, timezone, mail sender and HTTPS must be explicitly configured.

## Release gates (all mandatory)

1. Work on one **committed** release SHA, not copied individual files from unrelated branches. Keep exact SHA pinned in deployment manifest.
2. Resolve all required draft PRs with code review and **no blind mass-merge**. Compare changed files, schema and contracts.
3. GitHub ERP CI, Finance MySQL integration, payroll/HR, WebAuthn and PDF layout smoke (when applicable) all pass on this exact SHA.
4. Run full WAMP preflight with isolated test DB, confirm platform PHP extensions, expected migrations, Vite build, and clean Git tree. Do NOT run PHPUnit against populated MySQL.
5. Stage on VPS with isolated DB; run full schema migrate only on **empty staging DB**, then test POS/QR/KDS/stock/branch access/supplier transfers/GL/report PDF; stop on any discrepancy.
6. Backup and prove restore of any existing business database and uploads. Only then use reviewed additive migrations against live data.
7. Verify production `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_KEY` policy, secured Nginx and TLS, restricted DB, backups, queue/systemd, scheduler and rollback plan.
8. Only after sign-off: switch traffic to tested release. Never run production demo seeders, `migrate:fresh` or `db:wipe`.

## Next safe local checks

```powershell
git status --short
git diff --check
composer check-platform-reqs
php artisan migrate:status
node --test tests/Frontend/customer-menu-order-flow.test.cjs
php artisan test tests/Unit/AttendanceWebauthnVerifierTest.php --debug
```

Do not resolve pending migrations by blindly running the entire migration set on populated local/production data. Capture the exact pending names and assess whether PR code and schema are in sync.
