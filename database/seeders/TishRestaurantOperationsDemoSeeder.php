<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialPeriod;
use App\Models\Location;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Supplement for the restaurant demo seed.
 * Only stages purchase orders and expenses: posted accounting and goods receipts
 * MUST be performed by real domain workflows, never synthetic ledger inserts.
 */
class TishRestaurantOperationsDemoSeeder extends Seeder
{
    private const GROUPS = [
        'MEAT' => ['توريدات البروتين', ['BEEF', 'CHICKEN', 'WINGS', 'TURKEY']],
        'BAKERY' => ['مخبوزات البرجر', ['BUN', 'MINI-BUN']],
        'PRODUCE' => ['الخضار والفواكه', ['LETTUCE', 'TOMATO', 'ONION', 'PICKLE', 'JALAPENO', 'POTATO', 'SWEET-POTATO', 'CABBAGE', 'CARROT', 'MUSHROOM', 'ORANGE', 'LEMON', 'MINT', 'MANGO', 'STRAWBERRY']],
        'DAIRY' => ['الأجبان والألبان', ['CHEDDAR', 'SWISS', 'BLUE', 'MOZZARELLA', 'MILK', 'ICECREAM']],
        'SAUCES' => ['الصلصات والتوابل', ['SAUCE', 'BBQ', 'RANCH', 'BUFFALO', 'HONEY', 'TRUFFLE', 'MAYO', 'GARLIC', 'SEASONING', 'CHOCOLATE', 'VANILLA', 'LOTUS']],
        'DRINKS' => ['توريدات المشروبات', ['COLA', 'COLA-ZERO', 'LEMON-SODA', 'ORANGE-SODA', 'WATER', 'SPARKLING', 'TEA']],
        'PANTRY' => ['المواد الجافة والزيوت', ['OIL', 'FLOUR', 'CRUMBS', 'NACHOS', 'OREO']],
        'FROZEN' => ['المواد المجمدة والنباتية', ['VEGGIE', 'FALAFEL']],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('TISH demo operations may not be seeded in production.');
        }

        $admin = User::where('username', 'admin')->firstOrFail();
        $branch = Location::where('code', 'B01')->where('type', 'branch')->firstOrFail();
        $currency = Currency::where('code', 'ILS')->firstOrFail();
        if (! Product::where('sku', 'BRG-ING-BEEF')->where('is_raw_material', true)->exists()) {
            throw new RuntimeException('Run BurgerRestaurantDemoSeeder first.');
        }

        DB::transaction(function () use ($admin, $branch, $currency): void {
            $suppliers = $this->suppliersAndRawMaterials($admin, $currency);
            $this->draftPurchaseOrders($suppliers, $admin, $branch, $currency);
            $this->expenseCategoriesAndDrafts($admin, $branch);
            $this->sampleCustomers($branch);
            $this->assertRawSuppliersOnly();
        });

        $this->command?->info('TISH demo: 8 raw-material suppliers, procurement drafts, expense drafts and example customers seeded.');
        $this->command?->warn('Financial drafts are NOT posted; use actual purchase receiving/payment/expense workflows for ledger entries.');
    }

    private function suppliersAndRawMaterials(User $admin, Currency $currency): array
    {
        $suppliers = [];
        foreach (self::GROUPS as $code => [$label, $ingredientCodes]) {
            $supplier = Supplier::firstOrCreate(
                ['supplier_code' => 'TISH-SUP-'.$code],
                [
                    'name' => $label.' — مورد تجريبي',
                    'company_name' => 'TISH DEMO '.$code,
                    'contact_person' => 'مسؤول التوريد التجريبي',
                    'currency_id' => $currency->id,
                    'payment_terms' => 'net_14',
                    'credit_limit' => 25000,
                    'opening_balance' => 0,
                    'status' => 'active',
                    'city' => 'غزة',
                    'country' => 'فلسطين',
                    'notes' => 'DEMO ONLY — بيانات مورد وهمية للتدريب، لا تستخدم للشراء الحقيقي.',
                    'created_by' => $admin->id,
                ]
            );
            $suppliers[$code] = $supplier;

            SupplierContact::firstOrCreate(
                ['supplier_id' => $supplier->id, 'name' => 'مسؤول التوريد التجريبي'],
                ['position' => 'المبيعات', 'is_primary' => true, 'notes' => 'جهة اتصال وهمية']
            );

            foreach ($ingredientCodes as $ingredientCode) {
                $product = Product::query()
                    ->where('sku', 'BRG-ING-'.$ingredientCode)
                    ->where('is_raw_material', true)
                    ->firstOrFail();
                $price = BurgerRestaurantDemoSeeder::ingredients()[$ingredientCode][2];
                SupplierProduct::firstOrCreate(
                    ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                    [
                        'supplier_sku' => 'TISH-'.$code.'-'.$ingredientCode,
                        'supplier_product_name' => $product->name_ar ?: $product->name,
                        'purchase_price' => $price,
                        'currency_id' => $currency->id,
                        'minimum_order_quantity' => 1,
                        'conversion_factor' => 1,
                        'lead_time_days' => $code === 'PRODUCE' ? 1 : 2,
                        'is_preferred' => true,
                        'is_active' => true,
                        'notes' => 'عرض سعر تجريبي، ليس تعاقداً حقيقياً.',
                    ]
                );
            }
        }

        // Every raw ingredient must have a supplier; prepared menu items must have none.
        $unlinked = Product::query()
            ->where('sku', 'like', 'BRG-ING-%')
            ->where('is_raw_material', true)
            ->whereDoesntHave('supplierProducts')
            ->pluck('sku')->all();
        if ($unlinked !== []) {
            throw new RuntimeException('Raw ingredients missing suppliers: '.implode(', ', $unlinked));
        }

        return $suppliers;
    }

    private function draftPurchaseOrders(array $suppliers, User $admin, Location $branch, Currency $currency): void
    {
        foreach (self::GROUPS as $code => [$label, $ingredientCodes]) {
            $number = 'TISH-DEMO-PO-'.$code;
            $supplier = $suppliers[$code];
            $order = PurchaseOrder::firstOrCreate(
                ['purchase_order_number' => $number],
                [
                    'supplier_id' => $supplier->id,
                    'location_id' => $branch->id,
                    'currency_id' => $currency->id,
                    'exchange_rate' => 1,
                    'order_date' => now()->toDateString(),
                    'expected_delivery_date' => now()->addDays(3)->toDateString(),
                    'status' => 'draft',
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'shipping_cost' => 0,
                    'grand_total' => 0,
                    'base_grand_total' => 0,
                    'notes' => 'DEMO: مسودة مشتريات مواد خام — '. $label,
                    'created_by' => $admin->id,
                ]
            );
            $subtotal = 0.0;
            foreach (array_slice($ingredientCodes, 0, 4) as $ingredientCode) {
                $mapping = SupplierProduct::query()
                    ->where('supplier_id', $supplier->id)
                    ->whereHas('product', fn ($q) => $q->where('sku', 'BRG-ING-'.$ingredientCode))
                    ->firstOrFail();
                $product = $mapping->product;
                $quantity = 5.0;
                $unitPrice = (float) $mapping->purchase_price;
                $lineTotal = round($quantity * $unitPrice, 2);
                PurchaseOrderItem::firstOrCreate(
                    ['purchase_order_id' => $order->id, 'product_id' => $product->id],
                    [
                        'supplier_product_id' => $mapping->id,
                        'supplier_sku_snapshot' => $mapping->supplier_sku,
                        'purchase_unit_snapshot' => $product->unit,
                        'conversion_factor' => 1,
                        'description' => $product->name_ar ?: $product->name,
                        'ordered_quantity' => $quantity,
                        'ordered_base_quantity' => $quantity,
                        'received_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'line_total' => $lineTotal,
                        'base_unit_cost' => $unitPrice,
                        'base_line_total' => $lineTotal,
                    ]
                );
            }
            $subtotal = (float) $order->items()->sum('line_total');
            if ($order->statusValue() === 'draft') {
                $order->update([
                    'subtotal' => $subtotal,
                    'grand_total' => $subtotal,
                    'base_grand_total' => $subtotal,
                ]);
            }
        }
    }

    private function expenseCategoriesAndDrafts(User $admin, Location $branch): void
    {
        $period = FinancialPeriod::query()->where('status', 'open')->orderByDesc('id')->firstOrFail();
        $cash = PaymentMethod::where('code', 'CASH')->firstOrFail();
        $examples = [
            ['RENT', 'إيجار المطعم', 'occupancy', 2800],
            ['ELECTRIC', 'كهرباء المطعم', 'utilities', 600],
            ['WATER', 'المياه', 'utilities', 180],
            ['GAS', 'غاز المطبخ', 'operating', 260],
            ['CLEANING', 'مستلزمات التنظيف', 'operating', 170],
            ['MARKETING', 'تسويق المطعم', 'selling', 450],
            ['REPAIRS', 'صيانة أجهزة المطبخ', 'maintenance', 380],
            ['OFFICE', 'قرطاسية وإدارة', 'administrative', 90],
        ];
        foreach ($examples as $index => [$code, $title, $classification, $amount]) {
            $category = ExpenseCategory::firstOrCreate(['code' => 'TISH-'.$code], [
                'name' => $title,
                'classification' => $classification,
                'is_active' => true,
                'is_system' => false,
                'sort_order' => ($index + 1) * 10,
            ]);
            Expense::firstOrCreate(
                ['expense_number' => 'TISH-DEMO-EXP-'.$code],
                [
                    'expense_category_id' => $category->id,
                    'payment_method_id' => $cash->id,
                    'location_id' => $branch->id,
                    'financial_period_id' => $period->id,
                    'amount' => $amount,
                    'expense_date' => now()->toDateString(),
                    'payee' => 'مزود خدمة تجريبي',
                    'description' => 'DEMO: '.$title.' — غير مرحل مالياً',
                    'status' => 'draft',
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    private function sampleCustomers(Location $branch): void
    {
        foreach ([
            ['TISH-DEMO-CUST-01', 'زبون صالة تجريبي'],
            ['TISH-DEMO-CUST-02', 'زبون طلبات خارجية تجريبي'],
            ['TISH-DEMO-CUST-03', 'زبون متكرر تجريبي'],
            ['TISH-DEMO-CUST-04', 'زبون عائلي تجريبي'],
        ] as [$phone, $name]) {
            Customer::firstOrCreate(['phone' => $phone], [
                'location_id' => $branch->id,
                'name' => $name,
                'customer_type' => 'individual',
                'scope' => 'branch',
                'allow_credit' => false,
                'notes' => 'DEMO ONLY',
            ]);
        }
    }

    private function assertRawSuppliersOnly(): void
    {
        if (SupplierProduct::query()
            ->whereHas('supplier', fn ($q) => $q->where('supplier_code', 'like', 'TISH-SUP-%'))
            ->whereHas('product', fn ($q) => $q->where('is_raw_material', false))
            ->exists()) {
            throw new RuntimeException('TISH demo suppliers must only supply raw materials.');
        }
        if (PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('purchase_order_number', 'like', 'TISH-DEMO-PO-%'))
            ->whereHas('product', fn ($q) => $q->where('is_raw_material', false))
            ->exists()) {
            throw new RuntimeException('A demo purchase order contains a prepared menu item.');
        }
    }
}
