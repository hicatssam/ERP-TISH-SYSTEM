# Tish customer menu — code audit and regression checklist

## Scope and limitations
Reviewed source paths for public menu routing, branch menu/catalog, favorites, product customization,
cart storage, checkout request validation, customer-order creation/payment checks,
order tracking, receipt export, KDS-to-customer status derivation and shared navigation.
This is a static code review only. Real end-to-end behavior, mobile audio/permissions,
database transactions and browser rendering require running the app with a disposable test database.
Do **not** treat this document as a claim that every flow has been verified.

## Changes in this branch
- Correctly handle legacy cart quantities and malformed modifiers; reject non-finite/negative prices.
- Prevent invalid/negative quantity changes from corrupting a cart.
- Match price filter labels at boundaries (under 20; 20–50 inclusive; over 50).
- Drop stale dining-table selection when switching to takeaway/delivery.
- Clear a previous payment proof when selecting a different payment method.
- Offer a retry control when loading payment methods fails.
- Show the **existing** five-tab customer navigation component on desktop,
  including the orders/tracking views, without duplicating markup.

## Manual regression matrix (not executed)
| # | Flow | Check |
|---|---|---|
| 1 | Branches | Active branch selection and branch-code isolation |
| 2 | Home | Cover, client logo, dynamic banners, categories, search and empty state |
| 3 | Catalog | Category filters, under 20 / 20–50 / over 50, sorting and mobile scroll |
| 4 | Product | Variants, required/optional modifiers, quantities, price and addons |
| 5 | Favorites | Toggle from home/products/detail, totals and reload persistence |
| 6 | Cart | Add same/different configurations, quantity +/- 1–50, remove and reload |
| 7 | Legacy cart | Invalid quantity, removed product/variant/modifier and bad stored JSON |
| 8 | Checkout | Dine-in occupied table, takeaway/delivery switching and address validation |
| 9 | Payment | Cash, card, bank/wallet, account matching and required payment proof |
| 10 | Payment faults | Offline loading retry, 419 CSRF, 413 upload, 422 validation and duplicate submit |
| 11 | New order | Branch ownership, idempotent retry, order notification, draft to confirmed |
| 12 | Tracking | Received/accepted/preparing/ready/completed/cancelled and fresh KDS polling |
| 13 | Notifications | Opt-in chime/permission, background limitation, arrival of branch message |
| 14 | Receipt | Actual invoice before/after confirmation, PNG share, tax/discount and close |
| 15 | Navigation | Five bottom tabs, badges, active link, mobile and desktop, RTL/LTR |
| 16 | Display | No overlap for sticky cart, install button, dialogs and bottom navigation |
| 17 | Protection | Public token isolation, unavailable products, invalid branch/table/payment accounts |
| 18 | Accessibility | Keyboard focus, touch controls, reduced motion and screen reader notices |

## Acceptance
Run `php artisan test` and real browser tests on a test DB **before** merging.
Confirm no real payments or kitchen production transactions were created while testing.
