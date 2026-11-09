<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Services\Finance\LedgerReconciliationAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Audit only. There is intentionally no --apply flag: old bookings require
 * original documents, period controls and human approval before repair.
 */
class AuditFinancialLedger extends Command
{
    protected $signature = 'finance:audit-ledger
        {--from= : Inclusive transaction date YYYY-MM-DD}
        {--to= : Inclusive transaction date YYYY-MM-DD}
        {--location= : Optional location ID}
        {--limit=500 : Maximum missing source details}
        {--save : Store a JSON audit under the private local disk}';

    protected $description = 'Read-only audit of unposted ERP finance operations and historical ledger gaps';

    public function handle(LedgerReconciliationAuditService $audits): int
    {
        try {
            $today = CarbonImmutable::today();
            $from = $this->option('from') ?: $today->startOfMonth()->toDateString();
            $to = $this->option('to') ?: $today->toDateString();
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
            $finish = CarbonImmutable::createFromFormat('!Y-m-d', $to);

            if (! $start || ! $finish || $start->toDateString() !== $from
                || $finish->toDateString() !== $to || $start->greaterThan($finish)) {
                throw ValidationException::withMessages(['date' => 'تحقق من الفترة المطلوبة YYYY-MM-DD.']);
            }
            $locationId = $this->option('location') ? (int) $this->option('location') : null;
            if ($locationId !== null && ! Location::query()->whereKey($locationId)->exists()) {
                throw ValidationException::withMessages(['location' => 'الفرع المحدد غير موجود.']);
            }

            $audit = $audits->report($from, $to, $locationId, (int) $this->option('limit'));

            $this->table(['Source', 'Unposted'], collect($audit['summary'])
                ->map(fn ($row, $source) => [$source, $row['unlinked']])->values()->all());
            $this->line('Unposted source records: '.$audit['missing_total']);
            $this->line('Missing cost snapshots: '.$audit['missing_cost_items']);
            $this->line('Unbalanced journal headers: '.count($audit['unbalanced_journals']));
            if ($audit['truncated']) {
                $this->warn('Audit details are capped. Narrow the date or branch to inspect all rows.');
            }

            if ($this->option('save')) {
                $name = 'finance-audits/ledger-'.$today->format('Ymd').'-'.bin2hex(random_bytes(6)).'.json';
                Storage::disk('local')->put($name,
                    json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->info('Private audit saved: storage/app/private/'.$name);
            }

            $this->warn('READ ONLY: No past transactions or journals were modified.');
            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error(implode(' ', array_merge(...array_values($e->errors()))));
            return self::FAILURE;
        }
    }
}
