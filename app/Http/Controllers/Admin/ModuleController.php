<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModuleType;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    /**
     * Dahab is a food-service installation.
     * Retail/fashion catalog modules stay in the database for historical
     * compatibility, but are intentionally invisible and unavailable here.
     */
    private const HIDDEN_MODULE_CODES = [
        'clothing',
        'shoes',
        'product_variants',
        'sizes',
        'colors',
        'brands',
    ];

    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $query = Module::query()
            ->whereNotIn('code', self::HIDDEN_MODULE_CODES)
            ->with([
                'dependencies' => fn ($query) =>
                    $query->whereNotIn(
                        'modules.code',
                        self::HIDDEN_MODULE_CODES
                    ),
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->trim()->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return view('admin.modules.index', [
            'modules' => $query->get(),
            'types' => ModuleType::cases(),
            'bundles' => ModuleBundle::query()
                ->where('is_active', true)
                ->whereDoesntHave(
                    'modules',
                    fn ($query) => $query->whereIn(
                        'modules.code',
                        self::HIDDEN_MODULE_CODES
                    )
                )
                ->with([
                    'businessProfile',
                    'modules' => fn ($query) =>
                        $query->whereNotIn(
                            'modules.code',
                            self::HIDDEN_MODULE_CODES
                        ),
                ])
                ->orderBy('sort_order')
                ->get()
                ->filter(
                    fn (ModuleBundle $bundle) =>
                        $bundle->modules->isNotEmpty()
                )
                ->values(),
        ]);
    }

    public function update(
        Request $request,
        Module $module,
        ModuleService $modules
    ): RedirectResponse {
        $this->ensureAdmin($request);

        abort_if(
            in_array(
                $module->code,
                self::HIDDEN_MODULE_CODES,
                true
            ),
            404
        );

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $modules->setEnabled(
            $module,
            (bool) $validated['enabled'],
            $request->user()
        );

        return back()->with('success', 'تم تحديث حالة الوحدة بنجاح.');
    }

    public function applyBundle(
        Request $request,
        ModuleBundle $bundle,
        ModuleService $modules
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $result = $modules->applyBundle($bundle, $request->user());

        $message = 'تم تطبيق الحزمة.';
        if ($result['skipped'] !== []) {
            $message .= ' بعض الوحدات مسجلة للمستقبل ولم تُفعّل: ' . implode('، ', $result['skipped']);
        }

        return back()->with('success', $message);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'إدارة الوحدات متاحة للإدارة العليا فقط.'
        );
    }
}
