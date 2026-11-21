# TISH restaurant demo operations

## Scope

The default `DatabaseSeeder` continues to invoke `BurgerRestaurantDemoSeeder`. This now invokes `TishRestaurantOperationsDemoSeeder` after real restaurant sales are generated. No factory or legacy sweets records are created.

Current demo data:
- 1 branch (`B01`); restaurant menu: 76 sellable products with approved recipes
- 53 raw ingredients with opening stock and inventory movements
- 8 **fictional raw-material suppliers** (meat, bakery bread, produce, dairy, sauces, drinks, pantry, frozen/vegetarian)
- 53 supplier-product offers, **all raw ingredients**, no finished menu products
- 8 itemized purchase orders in **draft** (not received and not posted)
- 8 categorized expenses in **draft** (not paid, not posted)
- 6 preexisting staff accounts and role assignments (Admin, branch manager, cashier, chef, inventory manager, accountant)
- Existing cash shift, tables, kitchen stations, 12 POS orders, actual sales invoices and recipe inventory movements
- 4 additional example customers

**Important**: Posted financial results currently come from POS orders generated through the real restaurant order service; expense and purchasing entries are intentionally drafts. No fake GL entries, supplier payments, posted goods receipts or salary payments are inserted directly. That would make reconciliation unreliable. Later fixtures should use normal receipt, supplier invoice, payment, payroll and expense posting services, followed by explicit reconciliation assertions.

## Safe local execution

Use ONLY a disposable local testing database:
```powershell
php -d memory_limit=1G artisan test tests/Feature/BurgerRestaurantDemoSeederTest.php
```

For a dedicated blank disposable database, confirm `APP_ENV=local`, database connection and backups, then:
```powershell
php -d memory_limit=1G artisan db:seed --class=BurgerRestaurantDemoSeeder
```

Do not run `migrate:fresh` on any database holding real data. Never execute this demo seeder on production. Do not copy demo credentials to any public server.

## Outstanding for full enterprise demo

1. Test realistic purchase orders -> goods receiving -> supplier invoices -> supplier payments through domain services (including partial delivery/returns).
2. Expense approval/posting, petty cash, cash-session closing and variance reconciliation through application services.
3. Payroll/attendance/leave cases, deductions and pay-period settlement through HR services.
4. Multi-period, multi-branch comparable financial reporting, COGS, wastage, expiry, and stock valuation.
5. Browser UI tests for actual staff workflows; passing unit/feature tests alone do not prove UI completeness.
