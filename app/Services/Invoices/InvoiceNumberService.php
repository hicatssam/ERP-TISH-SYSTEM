<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\SystemSetting;
use App\Services\Procurement\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    /**
     * إنشاء رقم فاتورة جديد وفريد.
     *
     * مثال:
     * DH-00001
     * DH-00002
     * DH-00003
     */
    public static function generate(): string
    {
        $prefix = trim((string) SystemSetting::get('invoice_prefix', 'DH'));

        return app(DocumentNumberService::class)->next('sales_invoice', $prefix !== '' ? $prefix : 'DH');
    }
}
