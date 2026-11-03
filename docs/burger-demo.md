# Tish burger restaurant demo

The default DatabaseSeeder now calls BurgerRestaurantDemoSeeder. SweetsMenuSeeder is a compatibility entry point to the same restaurant demo and no longer creates cakes or sweets.

## Reset a local demo installation

Only run against the disposable local demo database. This command deletes all existing tables and records:

```bash
git pull origin main
php artisan optimize:clear
php artisan migrate:fresh --seed
```

Demo login: `admin` / `TishDemo@2026!`. Do not expose demo accounts publicly.

The full fixture refuses production execution and refuses to mix legacy products/locations into the restaurant dataset. It does not silently delete an existing dataset.

## Included data

- 76 menu products: beef, chicken and vegetarian burgers; fries; appetizers; eight sauces; cold drinks and milkshakes. No cake or sweets products.
- Two Tish branches (`B01`, `B02`) and a central kitchen (`FAC`).
- Approved ingredient recipes for all 76 products, ingredient stock at all three locations, and 16 restaurant tables.
- 24 real restaurant-service orders with payments, invoices, kitchen routing, recipe consumption and financial postings; 16 are completed.
- Three London telephone booth promotional banners per branch.
- Restaurant business profile and its available modules, with bakery and cake-order modules disabled.
- Demo employees and role permissions.

Prices, ingredient quantities and opening stock are illustrative training values. Review before operational use. Milkshake ingredients are raw materials, not a separate dessert menu.

The independent `php artisan db:seed --class=BurgerRestaurantSeeder` command only adds/backfills the menu catalogue and bundled images. It preserves other data and custom prices/images; it does not perform the full demo reset.

## Images

All menu products and categories use bundled WebP assets under `public/images/burger-menu/products/`; banners are under `public/images/burger-menu/banners/`. No third-party photo requests or storage symlink are required for these bundled assets.

Images are illustrative AI-generated catalogue photos. Product prompt set: a centered commercial studio food photograph of the named menu item on a warm pale beige background, with no labels or watermark. Sauce prompt set: one white dipping ramekin filled with the named sauce, three-quarter overhead, soft studio lighting and no unrelated food. Generated using the built-in image-generation workflow, then converted to WebP for delivery.

Catalogue reruns only fill empty image fields and preserve custom uploads.

## Verification

CI runs catalogue and complete-demo integration tests, including repeated seeding without duplicate orders or additional stock, rejection of legacy datasets, and physical existence of every menu image. The existing ERP regression suite also runs.
