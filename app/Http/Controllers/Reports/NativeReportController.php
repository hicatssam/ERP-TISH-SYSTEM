<?php

namespace App\Http\Controllers\Reports;

use App\Exports\MultiSectionReportExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\PayrollReportController;
use App\Http\Controllers\Admin\SalesChannelController;
use App\Http\Controllers\Finance\AccountingBooksController;
use App\Http\Controllers\Finance\AccountingLedgerController;
use App\Http\Controllers\Inventory\InventoryExpiryController;
use App\Http\Controllers\Procurement\ProcurementReportController;
use App\Http\Controllers\Restaurant\TakeawayDailyReportController;
use App\Http\Controllers\Sales\ShowroomCakeDailyReportController;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\ShowroomCakeDailyReport;
use App\Services\Finance\AccountingPdfService;
use App\Support\ReportCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class NativeReportController extends Controller
{
    public function export(Request $request, string $report, string $format)
    {
        $meta = app(ReportCatalog::class)->native()[$report] ?? null;
        abort_unless(\App\Support\ActivityModules::reportAllowed($report), 404);
        abort_unless($meta && in_array($format, ['xlsx', 'pdf', 'print'], true), 404);
        abort_unless(app(ReportCatalog::class)->allowed($request->user(), $meta), 403);
        $sections = $this->sections($request, $report);
        $title = $meta['title'];
        $total = array_sum(array_map(fn ($section) => $section['rows']->count(), $sections));
        $cap = max(1, (int) config($format === 'xlsx' ? 'reports.xlsx_row_cap' : 'reports.pdf_row_cap', $format === 'xlsx' ? 10000 : 500));
        $truncated = false;

        // Printing includes every filtered record; downloads disclose each capped section.
        if ($format !== 'print') {
            foreach ($sections as &$section) {
                if ($section['rows']->count() > $cap) {
                    $truncated = true;
                    $count = $section['rows']->count();
                    $section['rows'] = $section['rows']->take($cap)->values();
                    if ($format === 'xlsx') {
                        $section['rows']->push(["تنبيه: أول {$cap} سجل من أصل {$count}"]);
                    }
                }
            }
            unset($section);
        }
        if ($format === 'xlsx') {
            return Excel::download(new MultiSectionReportExport($sections), $report.'-'.now()->format('Ymd-His').'.xlsx');
        }
        $payload = compact('title', 'sections', 'total', 'truncated', 'cap');
        if ($format === 'pdf') {
            return app(AccountingPdfService::class)->download(
                'reports.native-document', $payload, $report.'-'.now()->format('Ymd-His').'.pdf', $title, 'landscape'
            );
        }

        return view('reports.native-document', $payload + ['pdfMode' => false]);
    }

    private function section(string $title, array $columns, iterable $rows): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => collect($rows)->values()];
    }

    private function sections(Request $request, string $report): array
    {
        switch ($report) {
            case 'takeaway':
                $d = app(TakeawayDailyReportController::class)->reportData($request);
                return [
                    $this->section('مبيعات Take Away', ['المنتج', 'SKU', 'السعر الحالي', 'الكمية المباعة', 'قيمة المبيعات', 'طلبات المنتج'], $d['rows']->map(fn ($r) => [
                        $r['name'], $r['sku'], $r['unit_price'], $r['sold_quantity'], $r['sales_total'], $r['orders_count'],
                    ])),
                    $this->section('الملخص', ['عدد المنتجات', 'الكمية', 'المبيعات', 'عدد الطلبات الفعلي'], [[
                        $d['totals']['products'], $d['totals']['sold_quantity'], $d['totals']['sales_total'], $d['totals']['orders_count'],
                    ]]),
                ];

            case 'payroll':
                $d = app(PayrollReportController::class)->reportData($request, false);
                return [$this->section('الرواتب', ['الدورة', 'الموظف', 'القسم', 'مركز التكلفة', 'الراتب الأساسي', 'البدلات', 'المكافآت', 'الخصومات', 'الإجمالي', 'الصافي', 'المدفوع', 'المتبقي', 'الحالة'], $d['rows']->map(fn ($r) => [
                    $r->period?->name, $r->employee?->full_name, $r->orgAssignment?->department?->name,
                    $r->orgAssignment?->costCenter?->name, (float) $r->base_salary, (float) $r->allowances_total,
                    (float) $r->bonuses_total, (float) $r->deductions_total, (float) $r->gross_salary,
                    (float) $r->net_salary, (float) ($r->paid_total ?? 0), (float) $r->payable_amount, $r->status,
                ])),
                $this->section('ملخص الرواتب', ['الموظفون', 'الإجمالي', 'الخصومات', 'الصافي', 'المتبقي'], [[
                    $d['summary']['employees'], $d['summary']['gross'], $d['summary']['deductions'], $d['summary']['net'], $d['summary']['payable'],
                ]]),
                $this->section('ملخص مراكز التكلفة', ['مركز التكلفة', 'الموظفون', 'صافي الرواتب'], $d['costCenterSummary']->map(fn ($r) => [
                    $d['costCenterNames'][$r->cost_center_id] ?? 'بدون مركز', (int) $r->employees, (float) $r->net,
                ]))];

            case 'channels':
                $d = app(SalesChannelController::class)->reportData($request, false);
                return [$this->section('قنوات البيع', ['الفترة', 'القناة', 'الطلبات', 'المبيعات الخام', 'خصم القناة', 'العمولة', 'صافي الإيراد'], $d['rows']->map(fn ($r) => [
                    $r->period, $r->sales_channel_name, (int) $r->orders_count, (float) $r->gross_sales,
                    (float) $r->channel_discounts, (float) $r->commissions, (float) $r->net_revenue,
                ]))];

            case 'procurement':
                $d = app(ProcurementReportController::class)->reportData($request);
                return [
                    $this->section('استلامات المواقع', ['الموقع', 'الكمية المقبولة', 'القيمة بالعملة الأساسية'], $d['receiptByLocation']->map(fn ($r) => [$r->name, (float) $r->accepted_quantity, (float) $r->base_total])),
                    $this->section('مشتريات الموردين', ['المورد', 'المشتريات بالعملة الأساسية', 'المتبقي بالعملة الأساسية'], $d['purchasesBySupplier']->map(fn ($r) => [$r->name, (float) $r->base_total, (float) $r->base_outstanding_total])),
                    $this->section('المرتجعات', ['المورد', 'الموقع', 'تاريخ المرتجع', 'العملة', 'القيمة الأساسية'], $d['returns']->map(fn ($r) => [$r->supplier?->name, $r->location?->name, $r->returned_at?->format('Y-m-d'), $r->currency?->code, (float) $r->base_grand_total])),
                    $this->section('أعمار الديون الحالية', ['الفاتورة', 'المورد', 'الموقع', 'الاستحقاق', 'أيام التأخير', 'الفئة', 'المتبقي', 'العملة'], $d['aging']->map(fn ($r) => [
                        $r['invoice']->invoice_number, $r['invoice']->supplier?->name, $r['invoice']->location?->name,
                        $r['invoice']->due_date?->format('Y-m-d'), $r['days_overdue'], $r['bucket'], (float) $r['invoice']->remaining_amount, $r['invoice']->currency?->code,
                    ])),
                    $this->section('أرصدة الموردين الحالية', ['المورد', 'العملة', 'الرصيد المستحق'], $d['supplierBalances']->map(fn ($r) => [$r['supplier']->name, $r['supplier']->currency?->code, (float) $r['summary']['outstanding_balance']])),
                ];

            case 'ledger':
                $d = app(AccountingLedgerController::class)->reportData($request, false, null);
                return [$this->section('الترحيلات المالية', ['التاريخ', 'الفرع', 'النوع', 'المرجع', 'المبلغ', 'العملة'], $d['entries']->map(fn ($r) => [
                    $r->entry_date?->format('Y-m-d'), $r->location?->name, $r->entry_type?->label() ?? $r->entry_type?->value,
                    ($r->reference_type ?? '') . ' #' . ($r->reference_id ?? $r->id), (float) $r->amount, $r->currency_code,
                ]))];

            case 'statements':
                $d = app(AccountingBooksController::class)->reportData($request)['report'];
                return [
                    $this->section('ميزان المراجعة', ['الكود', 'الحساب', 'افتتاحي مدين', 'افتتاحي دائن', 'حركة مدين', 'حركة دائن', 'ختامي مدين', 'ختامي دائن'], collect($d['rows'])->map(fn ($r) => [
                        $r['account']->code, $r['account']->name,
                        $r['opening_debit'] / 100, $r['opening_credit'] / 100, $r['period_debit'] / 100, $r['period_credit'] / 100, $r['closing_debit'] / 100, $r['closing_credit'] / 100,
                    ])),
                    $this->section('إجماليات ميزان المراجعة', ['افتتاحي مدين', 'افتتاحي دائن', 'حركة مدين', 'حركة دائن', 'ختامي مدين', 'ختامي دائن', 'فرق التوازن', 'العملة'], [[
                        $d['trial']['opening_debit'] / 100, $d['trial']['opening_credit'] / 100,
                        $d['trial']['period_debit'] / 100, $d['trial']['period_credit'] / 100,
                        $d['trial']['closing_debit'] / 100, $d['trial']['closing_credit'] / 100,
                        ($d['trial']['closing_debit'] - $d['trial']['closing_credit']) / 100, $d['currency']->code,
                    ]]),
                    $this->section('تفصيل الإيرادات والمصروفات', ['الحساب', 'نوع الحساب', 'قيمة الفترة', 'العملة'], collect($d['rows'])
                        ->filter(fn ($r) => in_array($r['account']->type, ['revenue', 'expense'], true))
                        ->map(fn ($r) => [$r['account']->code . ' · ' . $r['account']->name, $r['account']->type,
                            ($r['account']->type === 'revenue' ? $r['period_credit'] - $r['period_debit'] : $r['period_debit'] - $r['period_credit']) / 100, $d['currency']->code])),
                    $this->section('قائمة الدخل', ['الإيرادات', 'المصروفات', 'الصافي', 'العملة'], [[
                        $d['income']['revenue'] / 100, $d['income']['expenses'] / 100, $d['income']['net'] / 100, $d['currency']->code,
                    ]]),
                    $this->section('المركز المالي', ['الأصول', 'الالتزامات', 'حقوق الملكية', 'الأرباح المتراكمة', 'الالتزامات وحقوق الملكية', 'فرق التوازن', 'العملة'], [[
                        $d['position']['assets'] / 100, $d['position']['liabilities'] / 100, $d['position']['equity'] / 100,
                        $d['position']['cumulative_earnings'] / 100, $d['position']['liabilities_and_equity'] / 100, $d['position']['difference'] / 100, $d['currency']->code,
                    ]]),
                    $this->section('تنبيهات المطابقة', ['عمليات غير مرتبطة بقيود محاسبية'], [[$d['unlinked_operations']]]),
                ];

            case 'expiry':
                $d = app(InventoryExpiryController::class)->print($request)->getData();
                return [$this->section('دفعات المخزون', ['الدفعة', 'المنتج', 'الموقع', 'الصلاحية', 'الكمية المتاحة', 'الأيام المتبقية'], $d['rows']->map(fn ($r) => [
                    $r->batch_number, $r->product_name, $r->location_name, $r->expiry_date, (float) $r->available_quantity, $r->days_left,
                ]))];

            case 'showroom-sweets':
                $d = app(ShowroomSweetsRequestsReportController::class)->print($request)->getData();
                return [$this->section('طلبات حلويات الفروع', ['رقم الطلب', 'الفرع', 'التاريخ', 'الحالة', 'المنتج', 'الكمية المطلوبة', 'الكمية المحجوزة'], $d['requests']->flatMap(fn ($r) => $r->items->map(fn ($item) => [
                    $r->request_number ?? $r->id, $r->requestingLocation?->name, $r->created_at?->format('Y-m-d'),
                    $r->status?->label() ?? $r->status?->value, $item->displayProductName(),
                    (float) $item->quantity, (float) $item->reserved_quantity,
                ])))];

            case 'showroom-cake':
                $request->validate(['report_id' => ['required', 'integer']]);
                $model = ShowroomCakeDailyReport::findOrFail($request->integer('report_id'));
                $d = app(ShowroomCakeDailyReportController::class)->print($request, $model)->getData();
                $raw = $d['rawMaterialReport'] ?? ['requests' => collect(), 'dispatches' => collect(), 'receipts' => collect()];
                $movements = collect($raw['dispatches'])->map(fn ($line) => ['إرسال', $line])
                    ->concat(collect($raw['receipts'])->map(fn ($line) => ['استلام', $line]));
                return [
                    $this->section('تقرير قسم الكيك', ['التصنيف', 'افتتاحي', 'المستلم', 'الإجمالي', 'المباع', 'الجرد الفعلي', 'الفرق', 'ملاحظة'], $d['visibleItems']->map(fn ($row) => [
                        $row->category_name, $row->opening_qty, $row->received_qty, $row->total_qty, $row->sold_qty, $row->actual_closing_qty, $row->variance_qty, $row->note,
                    ])),
                    $this->section('طلبات المواد الخام للفرع', ['رقم طلب الخام', 'المادة الخام', 'الكمية المطلوبة', 'الكمية المعتمدة', 'حالة الطلب'], collect($raw['requests'])->flatMap(fn ($stock) =>
                        $stock->items->filter(fn ($line) => (bool) $line->product?->is_raw_material)
                            ->map(fn ($line) => [
                                $stock->request_number,
                                $line->product?->name_ar ?: ($line->product?->name ?? '—'),
                                (float) $line->requested_quantity,
                                $line->approved_quantity === null ? '—' : (float) $line->approved_quantity,
                                $stock->status?->label() ?? (string) $stock->status,
                            ])
                    )),
                    $this->section('إرسال واستلام المواد الخام', ['نوع الحركة', 'رقم التحويل', 'المادة الخام', 'الكمية', 'سعر التكلفة المسجل', 'القيمة التكلفية', 'الوحدة'], $movements->map(fn ($pair) => [
                        $pair[0], $pair[1]['transfer']->transfer_number,
                        $pair[1]['product']?->name_ar ?: ($pair[1]['product']?->name ?? '—'),
                        $pair[1]['quantity'],
                        $pair[1]['cost_recorded'] ? $pair[1]['unit_cost'] : 'غير مسجل',
                        $pair[1]['cost_recorded'] ? $pair[1]['value'] : 'غير مكتملة',
                        $pair[1]['product']?->unitDefinition?->symbol ?: $pair[1]['product']?->unit,
                    ])),
                ];

            case 'attendance':
                $request->merge(['from' => $request->input('from') ?: now()->startOfMonth()->toDateString(), 'to' => $request->input('to') ?: today()->toDateString()]);
                $dates = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
                $ids = Employee::query()->accessibleBy($request->user())->select('employees.id');
                $rows = AttendanceRecord::query()->whereIn('employee_id', $ids)->with('employee:id,full_name')
                    ->whereBetween('work_date', [$dates['from'], $dates['to']])
                    ->where(fn ($q) => $q->whereIn('status', ['absent', 'leave'])->orWhere('late_minutes', '>', 0)->orWhere('early_leave_minutes', '>', 0)->orWhere('overtime_minutes', '>', 0))
                    ->orderBy('work_date')->orderBy('id')->get();
                return [$this->section('استثناءات الحضور', ['الموظف', 'التاريخ', 'الحالة', 'دقائق العمل', 'دقائق التأخير', 'الانصراف المبكر', 'الإضافي'], $rows->map(fn ($r) => [
                    $r->employee?->full_name, $r->work_date?->format('Y-m-d'), $r->status, $r->worked_minutes, $r->late_minutes, $r->early_leave_minutes, $r->overtime_minutes,
                ]))];
        }

        abort(404);
    }
}
