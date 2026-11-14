<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Finance\LedgerReconciliationAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialAuditController extends Controller
{
    public function __construct(private readonly LedgerReconciliationAuditService $audit) {}

    /**
     * Read-only exception register. A global user must select a branch;
     * never aggregate historical branches without an explicit decision.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $user = $request->user();
        $canViewAll = $user->isAdmin() || $user->can('financial.global.view');
        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;

        if (! $canViewAll) {
            $primaryId = (int) ($user->primaryLocation()?->id ?? 0);
            abort_unless($primaryId > 0 && (! $locationId || $locationId === $primaryId), 403);
            $locationId = $primaryId;
        } elseif (! $locationId) {
            // For TISH B01 is normally the only active branch. If there is
            // more than one, the user must choose; no silent all-branch sum.
            $active = Location::query()->where('is_active', true)->limit(2)->pluck('id');
            $locationId = $active->count() === 1 ? (int) $active->first() : null;
        }

        $from = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['date_to'] ?? now()->toDateString();

        return view('finance.audit.index', [
            'report' => $locationId ? $this->audit->report($from, $to, $locationId, 200) : null,
            'locations' => $canViewAll ? Location::query()->orderByDesc('is_active')->orderBy('name')->get() : collect(),
            'locationId' => $locationId,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
