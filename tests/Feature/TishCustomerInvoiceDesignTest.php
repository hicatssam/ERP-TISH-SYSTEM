<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TishCustomerInvoiceDesignTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_order_invoice_uses_paper_design_and_actual_invoice_amounts(): void
    {
        $location = Location::make(['code' => 'tish-test', 'name' => 'الفرع الرئيسي']);
        $order = Order::make(['public_token' => 'tish-public-test-token']);
        $order->setRelation('location', $location);
        $order->setRelation('items', collect());

        $invoice = Invoice::make([
            'invoice_number' => 'TSH-2026-00025',
            'subtotal' => 58,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 58,
            'issued_at' => '2026-11-05 12:27:00',
        ]);
        $invoice->setRelation('location', $location);
        $invoice->setRelation('currency', null);
        $invoice->setRelation('items', collect([
            InvoiceItem::make([
                'description' => 'أصابع موزاريلا',
                'quantity' => 1,
                'unit_price' => 16,
                'line_total' => 16,
            ]),
            InvoiceItem::make([
                'description' => 'دبل تشيز برجر',
                'quantity' => 1,
                'unit_price' => 32,
                'line_total' => 32,
            ]),
            InvoiceItem::make([
                'description' => 'فرايز وسط',
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
            ]),
        ]));

        $html = view('customer-menu.invoice', [
            'order' => $order,
            'invoice' => $invoice,
            'branding' => ['name' => 'مطعم Tish', 'logo' => null],
        ])->render();

        $this->assertStringContainsString('tish-invoice-v2.css', $html);
        $this->assertStringContainsString('tish-invoice-paper', $html);
        $this->assertStringContainsString('TSH-2026-00025', $html);
        $this->assertStringContainsString('أصابع موزاريلا', $html);
        $this->assertStringContainsString('دبل تشيز برجر', $html);
        $this->assertStringContainsString('58.00', $html);
        $this->assertStringNotContainsString('class="invoice-party-table"', $html);
    }
}
