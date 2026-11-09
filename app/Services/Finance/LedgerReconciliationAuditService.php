<?php

namespace App\Services\Finance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only audit. Never inserts financial journals or alters historic sources.
 * Results are deliberately capped; a human must review missing sources.
 */
class LedgerReconciliationAuditService
{
    /** @return array<string, mixed> */
    public function report(string $from, string $to, ?int $locationId = null, int $limit = 500): array
    {
        $limit = min(5000, max(1, $limit));
        $end = Carbon::parse($to)->addDay()->toDateString();
        $periods = Schema::hasTable('financial_periods')
            ? DB::table('financial_periods')->get(['start_date', 'end_date', 'status'])
            : collect();

        $sources = [
            ['table' => 'sales_ledger_entries', 'date' => 'entry_date', 'amount' => 'amount',
                'statuses' => null, 'excluded' => null],
            ['table' => 'customer_payments', 'date' => 'paid_at', 'amount' => 'amount',
                'statuses' => ['confirmed'], 'excluded' => null],
            ['table' => 'supplier_invoices', 'date' => 'invoice_date', 'amount' => 'base_grand_total',
                'statuses' => null, 'excluded' => ['cancelled']],
            ['table' => 'supplier_payments', 'date' => 'payment_date', 'amount' => 'base_amount',
                'statuses' => ['confirmed'], 'excluded' => null],
            ['table' => 'purchase_returns', 'date' => 'posted_at', 'amount' => 'base_grand_total',
                'statuses' => ['posted'], 'excluded' => null],
            ['table' => 'payroll_payments', 'date' => 'paid_at', 'amount' => 'base_amount',
                'statuses' => ['posted', 'voided'], 'excluded' => null],
            ['table' => 'branch_cash_movements', 'date' => 'occurred_at', 'amount' => 'amount',
                'statuses' => null, 'excluded' => null],
        ];

        $summary = [];
        $missing = [];
        foreach ($sources as $spec) {
            $table = $spec['table'];
            if (! Schema::hasTable($table)) {
                $summary[$table] = ['unlinked' => 0, 'missing_table' => true];
                continue;
            }

            $query = DB::table($table.' as source')
                ->leftJoin('accounting_journals as posted', function ($join) use ($table): void {
                    $join->on('posted.source_id', '=', 'source.id')
                        ->where('posted.source_type', '=', $table);
                })
                ->whereNull('posted.id')
                ->where('source.'.$spec['date'], '>=', $from)
                ->where('source.'.$spec['date'], '<', $end);
            if ($locationId) {
                $query->where('source.location_id', $locationId);
            }
            if ($spec['statuses'] !== null) {
                $query->whereIn('source.status', $spec['statuses']);
            }
            if ($spec['excluded'] !== null) {
                $query->whereNotIn('source.status', $spec['excluded']);
            }
            if ($table === 'sales_ledger_entries') {
                $query->whereIn('source.entry_type', [
                    'sale', 'sale_cancellation', 'payment_collection', 'payment_reversal',
                    'refund', 'expense', 'expense_reversal', 'employee_purchase', 'employee_collection',
                ]);
            }

            $summary[$table] = ['unlinked' => (int) (clone $query)->count()];
            $available = $limit - count($missing);
            if ($available <= 0) {
                continue;
            }
            $rows = (clone $query)->orderBy('source.id')->limit($available)
                ->get(['source.id', 'source.location_id',
                    'source.'.$spec['date'].' as transaction_date',
                    'source.'.$spec['amount'].' as base_amount']);

            foreach ($rows as $row) {
                $date = substr((string) $row->transaction_date, 0, 10);
                $period = $periods->first(fn ($p) => substr((string) $p->start_date, 0, 10) <= $date
                    && substr((string) $p->end_date, 0, 10) >= $date);
                $missing[] = [
                    'source' => $table,
                    'id' => (int) $row->id,
                    'location_id' => (int) $row->location_id,
                    'date' => $date,
                    'base_amount' => (string) $row->base_amount,
                    'period_status' => $period?->status ?? 'missing',
                    'action' => $period === null ? 'open_period_and_review'
                        : ($period->status === 'open' ? 'review_source_before_posting' : 'closed_period_manual_adjustment'),
                ];
            }
        }

        $unbalanced = [];
        if (Schema::hasTable('accounting_journals')) {
            $journalQuery = DB::table('accounting_journals')
                ->where('entry_date', '>=', $from)
                ->where('entry_date', '<', $end);
            if ($locationId) {
                $journalQuery->where('location_id', $locationId);
            }
            $unbalanced = $journalQuery->whereRaw('ABS(total_debit - total_credit) > 0.004')
                ->limit($limit)->get(['id', 'number', 'location_id', 'entry_date', 'total_debit', 'total_credit'])
                ->map(fn ($j) => (array) $j)->all();
        }

        $missingCost = 0;
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'cost_snapshotted_at')) {
            $costQuery = DB::table('order_items as item')
                ->join('orders as ord', 'ord.id', '=', 'item.order_id')
                ->where('ord.status', 'confirmed')
                ->whereNull('item.cost_snapshotted_at')
                ->where('ord.confirmed_at', '>=', $from)
                ->where('ord.confirmed_at', '<', $end);
            if ($locationId) {
                $costQuery->where('ord.location_id', $locationId);
            }
            $missingCost = $costQuery->count();
        }

        return [
            'filters' => ['from' => $from, 'to' => $to, 'location_id' => $locationId, 'limit' => $limit],
            'summary' => $summary,
            'missing' => $missing,
            'missing_total' => array_sum(array_column($summary, 'unlinked')),
            'missing_cost_items' => $missingCost,
            'unbalanced_journals' => $unbalanced,
            'truncated' => count($missing) < array_sum(array_column($summary, 'unlinked')),
            'read_only' => true,
        ];
    }
}
