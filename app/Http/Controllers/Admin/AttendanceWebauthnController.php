<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeWebauthnCredential;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceWebauthnController extends Controller
{
    public function index(Request $request): View
    {
        $query = EmployeeWebauthnCredential::query()->with('employee')->latest();
        if (! $request->user()->isAdmin()) {
            $locationId = $request->user()->primaryLocation()?->id;
            abort_unless($locationId, 403);
            $query->whereHas('employee.employeeLocations', fn ($q) => $this->scopeLocation($q, $locationId));
        }

        return view('admin.attendance.webauthn', ['credentials' => $query->paginate(30)]);
    }

    public function approve(Request $request, EmployeeWebauthnCredential $credential): RedirectResponse
    {
        return $this->change($request, $credential, 'active');
    }

    public function revoke(Request $request, EmployeeWebauthnCredential $credential): RedirectResponse
    {
        return $this->change($request, $credential, 'revoked');
    }

    private function change(Request $request, EmployeeWebauthnCredential $credential, string $status): RedirectResponse
    {
        DB::transaction(function () use ($request, $credential, $status): void {
            // Same lock order as registration and attendance; revocation cannot race a punch.
            $employee = $credential->employee()->lockForUpdate()->firstOrFail();
            $credential = EmployeeWebauthnCredential::query()->whereKey($credential->id)->lockForUpdate()->firstOrFail();
            if (! $request->user()->isAdmin()) {
                $locationId = $request->user()->primaryLocation()?->id;
                abort_unless($locationId && $this->scopeLocation($employee->employeeLocations(), $locationId)->exists(), 403);
            }
            if ($status === 'active') {
                abort_if((int) $credential->user_id === (int) $request->user()->id
                    || (int) $request->user()->employee_id === (int) $employee->id, 403,
                    'يجب أن يعتمد مفتاحك مسؤول آخر.');
                if ($credential->status !== 'pending' || ! $employee->isActive()
                    || ! User::query()->whereKey($credential->user_id)->where('employee_id', $employee->id)->exists()) {
                    throw ValidationException::withMessages(['credential' => 'المفتاح غير قابل للاعتماد؛ تحقق من الموظف وحسابه.']);
                }
                $credential->update(['status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            } else {
                $credential->update(['status' => 'revoked', 'revoked_at' => now()]);
            }
            ActivityLogger::log(userId: $request->user()->id, action: 'attendance.webauthn.'.$status,
                module: 'attendance', recordType: 'employee_webauthn_credentials', recordId: $credential->id,
                newValues: ['employee_id' => $employee->id, 'status' => $status], ipAddress: $request->ip());
        });

        return redirect()->route('attendance.webauthn.index')->with('success',
            $status === 'active' ? 'تم اعتماد مفتاح الموظف.' : 'تم إلغاء مفتاح الموظف.');
    }

    private function scopeLocation($query, int $locationId)
    {
        return $query->where('location_id', $locationId)->where('is_primary', true)
            ->where(fn ($q) => $q->whereNull('started_at')->orWhereDate('started_at', '<=', now()->toDateString()))
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', now()->toDateString()));
    }
}
