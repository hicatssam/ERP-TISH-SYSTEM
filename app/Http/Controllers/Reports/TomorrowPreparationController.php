<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Finance\AccountingPdfService;
use App\Services\Reports\TomorrowPreparationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TomorrowPreparationExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TomorrowPreparationController extends Controller
{
    public function index(Request $request, TomorrowPreparationService $service): View
    {
        return view('reports.tomorrow-preparation.index', $this->payload($request, $service));
    }

    public function print(Request $request, TomorrowPreparationService $service): View
    {
        return view('reports.tomorrow-preparation.document', $this->payload($request, $service));
    }

    public function pdf(
        Request $request,
        TomorrowPreparationService $service,
        AccountingPdfService $pdf,
    ): StreamedResponse {
        $payload = $this->payload($request, $service);
        return $pdf->download(
            'reports.tomorrow-preparation.document',
            $payload,
            'tomorrow-preparation-'.$payload['date'].'.pdf',
            'خطة تجهيز طلبات الفروع',
            'landscape',
        );
    }

    public function excel(Request $request, TomorrowPreparationService $service): BinaryFileResponse
    {
        $payload = $this->payload($request, $service);
        return Excel::download(
            new TomorrowPreparationExport($payload),
            'tomorrow-preparation-'.$payload['date'].'.xlsx',
        );
    }

    private function payload(Request $request, TomorrowPreparationService $service): array
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $factory = $user->primaryLocation();

        // This report spans branches. Never expose it to a branch user with
        // generic reports.view permission.
        abort_unless($isAdmin || ($factory && $factory->isFactory()), 403);

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'factory_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q
                    ->where('type', 'factory')->where('is_active', true)->whereNull('deleted_at')),
            ],
        ]);

        if (! $isAdmin && filled($filters['factory_id'] ?? null)
            && (int) $filters['factory_id'] !== (int) $factory->id) {
            abort(403, 'لا تملك صلاحية عرض مصنع آخر.');
        }

        $date = $filters['date'] ?? now()->addDay()->toDateString();
        $factoryId = $isAdmin
            ? (isset($filters['factory_id']) ? (int) $filters['factory_id'] : null)
            : (int) $factory->id;

        $data = $service->build($date, $factoryId);
        $factories = $isAdmin
            ? Location::query()->factory()->active()->orderBy('name')->get(['id', 'name'])
            : collect([$factory]);
        $selectedFactory = $factoryId
            ? $factories->firstWhere('id', $factoryId)
            : null;

        return array_merge($data, [
            'factories' => $factories,
            'canChooseFactory' => $isAdmin,
            'selectedFactory' => $selectedFactory,
            'scopeLabel' => $selectedFactory?->name ?? 'جميع المصانع',
        ]);
    }
}
