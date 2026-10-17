<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\ShowroomCakeRequest;
use App\Models\ShowroomSweetsRequest;
use App\Services\Sales\BranchOrderDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchOrderBulkDeletionController extends Controller
{
    public function __invoke(
        Request $request,
        string $type,
        BranchOrderDeletionService $deleter
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);

        $models = [
            'cake' => ShowroomCakeRequest::class,
            'sweets' => ShowroomSweetsRequest::class,
        ];
        abort_unless(isset($models[$type]), 404);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['selected', 'all_eligible'])],
            'ids' => ['required_if:mode,selected', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'confirmation' => ['required_if:mode,all_eligible', 'nullable', 'string'],
        ]);

        if ($data['mode'] === 'all_eligible') {
            if (($data['confirmation'] ?? '') !== 'حذف الطلبات الملغاة') {
                return back()->withErrors([
                    'confirmation' => 'للتأكيد اكتب العبارة: حذف الطلبات الملغاة',
                ]);
            }
            $deleted = $deleter->deleteAllEligible($models[$type], $request->user());
        } else {
            $deleted = $deleter->deleteMany($models[$type], $data['ids'], $request->user());
        }

        return redirect()->route(
            $type === 'cake' ? 'showroom-cake-requests.index' : 'showroom-sweets-requests.index'
        )->with('success', "تمت أرشفة {$deleted} طلب من الطلبات الملغاة دون حذف سجلات المحاسبة والمخزون.");
    }
}
