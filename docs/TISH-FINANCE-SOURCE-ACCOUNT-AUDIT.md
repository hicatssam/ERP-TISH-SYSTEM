# TISH finance UX / account-origin implementation notes

## Implemented in this branch (not deployed)

- Supplier payment records identify the **specific branch source account**, supplier beneficiary name/account, payment method, and transfer reference.
- Non-cash supplier payments require an active account authorized for outward transfers; the source account must belong to the invoice's branch and the chosen method. Checks are server-side inside the same transaction as payment and ledger posting.
- Customer-visible payment accounts and outgoing disbursement authorization are **separate opt-in toggles** in branch account settings. Public restaurant menu excludes accounts not customer-visible.
- Source account and reference appear in supplier payment history; the view links to adjacent purchasing, expense and accounting screens, subject to permissions.
- Past cash payments and records without a source account are preserved.
- Previously used source accounts cannot be deleted or have their payment method reassigned; they can be deactivated.
- Validation test: `tests/Feature/Finance/SupplierPaymentSourceAccountTest.php`.

## Important boundaries / accounting safety

The current double-entry general ledger uses generic **1000 cash / 1010 bank** accounts based on payment method. This change stores the actual bank/wallet account per supplier payment, but **does not claim that the chart-of-accounts ledger now has separate balances per bank account**. A full treasury subledger and bank reconciliation must match all incoming collections, outgoing supplier payments, cash movements, refunds, payroll, transfers and opening balances to the same physical source account. Do not display a derived balance as an audited bank balance until that work is complete.

The existing `location_payment_accounts` was originally customer-facing. **Do not mark a private outgoing account as customer-visible**. The migration retains existing customer visibility by default and turns outward authorization off by default.

The supplier payment flow continues to require a supplier invoice; a one-step direct market purchase (supplier → inventory receipt → payment) remains a separate workflow task. Mobile wallet/bank methods are options, but external bank transfers are NOT actually initiated or verified by this ERP.

## Next full interface audit

1. Bank/wallet account registry: privacy, permissions, opening balances, reconciliation, duplicate account detection.
2. Procurement: supplier including informal vendors, PO, direct purchase, receipt, invoice, partial payment, return, reference/proof.
3. Finance: expenses fixed/variable, capex and asset register, employee-reimbursed cash purchases, multi-location costs.
4. Cash drawer/day reconciliation, inflows/outflows and transfers, safeguards for closed periods.
5. Employee advance, payroll, journal books, accounting ledger and tax documentation.
6. Cross-screen task navigation, empty states, quick actions, filtering, keyboard/mobile layouts and role-scoped access.

## Local validation (disposable test DB only)

```powershell
php -d memory_limit=1G artisan test tests/Feature/Finance/SupplierPaymentSourceAccountTest.php
php -d memory_limit=1G artisan test tests/Feature/Procurement --stop-on-failure
```

Review this draft PR; do not merge/deploy until the schema change, public customer menus, UI and treasury implications are verified. Never run `migrate:fresh` against existing TISH data.
