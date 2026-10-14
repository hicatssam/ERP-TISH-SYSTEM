<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\PermissionUi;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const GUARD_NAME = 'web';
    private const ADMIN_ROLE = 'Admin';

    public function index()
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD_NAME)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Read-only audit of the actual permissions currently saved in the database.
     * Never rewrite role grants during an audit.
     */
    public function audit()
    {
        $permissions = Permission::query()
            ->where('guard_name', self::GUARD_NAME)
            ->orderBy('name')
            ->get();

        $roleReports = Role::query()
            ->where('guard_name', self::GUARD_NAME)
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role): array {
                $knownLabels = PermissionUi::permissionLabels();

                $unlabelled = $role->permissions
                    ->filter(fn (Permission $permission) =>
                        ! array_key_exists($permission->name, $knownLabels))
                    ->values();

                // Elevated or cross-location rights are surfaced for human
                // review only; they are never revoked automatically.
                $reviewCandidates = [
                    'customers.view_all',
                    'employees.view_all',
                    'cake_orders.view_all',
                    'showroom_cake_requests.view_all',
                    'showroom_sweets_requests.view_all',
                    'delivery.view_all_locations',
                    'chat.view_all_branches',
                    'chat.direct.start_all',
                    'restaurant.view_all_locations',
                    'kitchen.view_all_locations',
                    'expenses.view_all_locations',
                    'financial.global.view',
                    'costing.view_all_locations',
                    'inventory.expiry-alerts.receive_all',
                    'roles.manage',
                    'users.manage',
                    'settings.manage',
                    'payroll.pay',
                    'payments.refund',
                    'financial.periods.override_close',
                ];

                $sensitive = in_array($role->name, ['Admin', 'super-admin', 'General Manager'], true)
                    ? collect()
                    : $role->permissions
                        ->filter(fn (Permission $permission) =>
                            in_array($permission->name, $reviewCandidates, true))
                        ->values();

                return [
                    'role' => $role,
                    'unknown' => $unlabelled,
                    'review' => $sensitive,
                    'groups' => $role->permissions->groupBy(
                        fn (Permission $permission) => explode('.', $permission->name)[0]
                    ),
                ];
            });

        $knownLabels = PermissionUi::permissionLabels();
        $knownGroups = PermissionUi::groupLabels();

        $unlabelled = $permissions
            ->filter(fn (Permission $permission) =>
                ! array_key_exists($permission->name, $knownLabels))
            ->values();

        $ungrouped = $permissions
            ->map(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->unique()
            ->filter(fn (string $group) => ! array_key_exists($group, $knownGroups))
            ->values();

        $routePermissionNames = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) =>
                is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => trim(explode(',', substr($middleware, 4))[0]))
            ->filter(fn (string $name) => str_contains($name, '.'))
            ->unique()
            ->values();

        $missingRegistered = $routePermissionNames
            ->diff($permissions->pluck('name'))
            ->values();

        return view('admin.roles.audit', compact(
            'permissions', 'roleReports', 'unlabelled',
            'ungrouped', 'missingRegistered'
        ));
    }

    public function create()
    {
        $permissions = $this->getGroupedPermissions();

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    config('permission.table_names.roles', 'roles'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(
                    config('permission.table_names.permissions', 'permissions'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],
        ]);

        $roleName = trim($validated['name']);

        if (in_array(strtolower($roleName), ['admin', 'super-admin'], true)) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'يوجد دور مدير أساسي بالفعل، ولا يمكن إنشاء دور مدير آخر.',
                ]);
        }

        DB::transaction(function () use ($validated, $roleName) {
            $role = Role::create([
                'name'       => $roleName,
                'guard_name' => self::GUARD_NAME,
            ]);

            $role->syncPermissions(
                array_values(array_unique($validated['permissions'] ?? []))
            );
        });

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with('success', 'تم إنشاء الدور وتوزيع الصلاحيات بنجاح.');
    }

    public function show(Role $role)
    {
        $this->ensureWebGuard($role);

        $role->load([
            'permissions' => function ($query) {
                $query->orderBy('name');
            },
        ]);

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        $this->ensureWebGuard($role);

        $role->load('permissions');

        $permissions = $this->getGroupedPermissions();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    config('permission.table_names.roles', 'roles'),
                    'name'
                )
                    ->where(function ($query) {
                        return $query->where(
                            'guard_name',
                            self::GUARD_NAME
                        );
                    })
                    ->ignore($role->getKey()),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(
                    config('permission.table_names.permissions', 'permissions'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],
        ]);

        $isAdminRole = $this->isAdminRole($role);

        if (
            !$isAdminRole &&
            in_array(strtolower(trim($validated['name'])), ['admin', 'super-admin'], true)
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'لا يمكن تحويل هذا الدور إلى دور المدير الأساسي.',
                ]);
        }

        DB::transaction(function () use ($role, $validated, $isAdminRole) {
            if ($isAdminRole) {
                /*
                 * اسم دور المدير لا يتغير.
                 * يحصل المدير على جميع صلاحيات web تلقائيًا.
                 */
                $allPermissions = Permission::query()
                    ->where('guard_name', self::GUARD_NAME)
                    ->pluck('name')
                    ->all();

                $role->syncPermissions($allPermissions);

                return;
            }

            $role->update([
                'name' => trim($validated['name']),
            ]);

            $role->syncPermissions(
                array_values(array_unique($validated['permissions'] ?? []))
            );
        });

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                $isAdminRole
                    ? 'تم تحديث دور المدير ومنحه جميع الصلاحيات.'
                    : 'تم تحديث الدور والصلاحيات بنجاح.'
            );
    }

    public function destroy(Role $role)
    {
        $this->ensureWebGuard($role);

        if ($this->isAdminRole($role)) {
            return back()->with('error', 'لا يمكن حذف دور المدير.');
        }

        if ($role->users()->exists()) {
            return back()->with(
                'error',
                'لا يمكن حذف هذا الدور لأنه مرتبط بمستخدمين. انقل المستخدمين إلى دور آخر أولًا.'
            );
        }

        $role->delete();

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with('success', 'تم حذف الدور بنجاح.');
    }

    private function getGroupedPermissions()
    {
        return Permission::query()
            ->where('guard_name', self::GUARD_NAME)
            ->orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission) {
                return explode('.', $permission->name)[0];
            });
    }

    private function ensureWebGuard(Role $role): void
    {
        abort_unless(
            $role->guard_name === self::GUARD_NAME,
            404
        );
    }

    private function isAdminRole(Role $role): bool
    {
        return in_array(strtolower($role->name), ['admin', 'super-admin'], true);
    }

    private function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}