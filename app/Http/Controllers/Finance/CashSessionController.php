<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\OpenCashSessionRequest;
use App\Http\Requests\Finance\CloseCashSessionRequest;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\Location;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Employee;

class CashSessionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = CashSession::with(['employee', 'location', 'closedBy']);

        if (! $user->isAdmin()) {
            $locationId = $user->primaryLocation()?->id;
            $query->where('location_id', $locationId);
        }

        $sessions = $query->latest('opened_at')->paginate(20);
        $activeSession = CashSession::where('employee_id', $user->employee?->id)
            ->where('status', 'open')->first();

        $pendingOrders = $activeSession
            ? $this->pendingOrderCount($activeSession)
            : 0;

        return view('finance.cash-sessions.index', compact('sessions', 'activeSession', 'pendingOrders'));
    }

    public function open(OpenCashSessionRequest $request)
    {
        $user     = Auth::user();
        $employee = $user->employee;

        if (! $employee) {
            return back()->with('error', 'لا يوجد سجل موظف مرتبط بهذا الحساب.');
        }

        $locationId = $user->primaryLocation()?->id;
        abort_unless($locationId, 403, 'لا يوجد موقع رئيسي مرتبط بالحساب.');

        $session = DB::transaction(function () use ($employee, $locationId, $request): CashSession {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if (CashSession::query()->where('employee_id', $employee->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['cash_session' => 'لديك جلسة كاشير مفتوحة بالفعل.']);
            }

            return CashSession::create([
                'employee_id' => $employee->id,
                'location_id' => $locationId,
                'opening_balance' => $request->validated('opening_balance'),
                'opened_at' => now(),
                'status' => 'open',
                'cash_received' => 0,
                'cash_refunds' => 0,
                'expected_cash' => $request->validated('opening_balance'),
            ]);
        });

        ActivityLogger::log(
            userId:     $user->id,
            action:     'cash_session.opened',
            module:     'finance',
            recordType: 'cash_sessions',
            recordId:   $session->id,
            oldValues:  null,
            newValues:  ['opening_balance' => $session->opening_balance, 'status' => 'open'],
            metadata:   ['location_id' => $locationId, 'employee_id' => $employee->id],
        );

        return back()->with('success', 'تم فتح جلسة الكاشير.');
    }

    public function close(CloseCashSessionRequest $request, CashSession $cashSession)
    {
        $this->authorize('close', $cashSession);

        $validated = $request->validated();
        $cashSession = DB::transaction(function () use ($cashSession, $validated): CashSession {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);
            if (($cashSession->status?->value ?? $cashSession->status) !== 'open') {
                throw ValidationException::withMessages(['cash_session' => 'الجلسة مغلقة بالفعل.']);
            }

            $cashierUserId = DB::table('users')
                ->where('employee_id', $cashSession->employee_id)
                ->value('id');
            abort_unless($cashierUserId, 422, 'لا يوجد مستخدم مرتبط بموظف جلسة الكاشير.');

            $pendingOrders = $this->pendingOrderCount($cashSession, (int) $cashierUserId);
            if ($pendingOrders > 0 && ! ($validated['acknowledge_pending_orders'] ?? false)) {
                throw ValidationException::withMessages([
                    'acknowledge_pending_orders' => "هناك {$pendingOrders} طلب معلق لهذا الكاشير. راجعها وأكّد تسليمها للوردية التالية قبل الإغلاق.",
                ]);
            }

            $cashReceived = (float) DB::table('payments as p')
                ->join('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
                ->where('p.received_by', $cashierUserId)
                ->where('p.location_id', $cashSession->location_id)
                ->whereIn('p.status', ['confirmed', 'corrected', 'refunded'])
                ->where('pm.type', 'cash')
                ->whereBetween('p.paid_at', [$cashSession->opened_at, now()])
                ->sum('p.amount');

            $cashReceived += (float) DB::table('customer_payments as cp')
                ->join('payment_methods as pm', 'pm.id', '=', 'cp.payment_method_id')
                ->where('cp.received_by', $cashierUserId)
                ->where('cp.location_id', $cashSession->location_id)
                ->where('cp.status', 'confirmed')
                ->where('pm.type', 'cash')
                ->whereBetween('cp.paid_at', [$cashSession->opened_at, now()])
                ->sum('cp.amount');

            $cashRefunds = (float) DB::table('refunds as r')
                ->join('payments as p', 'p.id', '=', 'r.payment_id')
                ->join('payment_methods as pm', 'pm.id', '=', 'r.payment_method_id')
                ->where('r.processed_by', $cashierUserId)
                ->where('p.location_id', $cashSession->location_id)
                ->where('pm.type', 'cash')
                ->whereBetween('r.processed_at', [$cashSession->opened_at, now()])
                ->sum('r.amount');

            $expected = round((float) $cashSession->opening_balance + $cashReceived - $cashRefunds, 2);
            $variance = round((float) $validated['actual_cash'] - $expected, 2);
            $note = trim((string) ($validated['closing_note'] ?? ''));
            if (abs($variance) >= 0.01 && mb_strlen($note) < 8) {
                throw ValidationException::withMessages([
                    'closing_note' => 'عند وجود عجز أو زيادة في الصندوق يجب تسجيل سبب أو ملاحظة من 8 أحرف على الأقل.',
                ]);
            }

            $cashSession->update([
                'status' => 'closed',
                'cash_received' => $cashReceived,
                'cash_refunds' => $cashRefunds,
                'expected_cash' => $expected,
                'actual_cash' => $validated['actual_cash'],
                'variance' => $variance,
                'closing_note' => $note !== '' ? $note : null,
                'closed_at' => now(),
                'closed_by' => Auth::id(),
            ]);

            return $cashSession->fresh();
        });

        $variance = $cashSession->variance;

        ActivityLogger::log(
            userId:     Auth::id(),
            action:     'cash_session.closed',
            module:     'finance',
            recordType: 'cash_sessions',
            recordId:   $cashSession->id,
            oldValues:  ['status' => 'open'],
            newValues:  ['status' => 'closed', 'actual_cash' => $validated['actual_cash'], 'variance' => $variance],
            metadata:   [
                'location_id' => $cashSession->location_id,
                'employee_id' => $cashSession->employee_id,
                'cash_received' => $cashSession->cash_received,
                'cash_refunds' => $cashSession->cash_refunds,
                'expected_cash' => $cashSession->expected_cash,
                'pending_orders_acknowledged' => (bool) ($validated['acknowledge_pending_orders'] ?? false),
                'closing_note' => $cashSession->closing_note,
            ],
        );

        return back()->with('success', 'تم إغلاق جلسة الكاشير.');
    }
    private function pendingOrderCount(CashSession $session, ?int $cashierUserId = null): int
    {
        $cashierUserId ??= (int) DB::table('users')
            ->where('employee_id', $session->employee_id)
            ->value('id');
        if (! $cashierUserId) return 0;

        // Only orders made by this cashier during this session. Other
        // cashiers' pending orders must not prevent their shift handover.
        return Order::query()
            ->where('location_id', $session->location_id)
            ->where('created_by', $cashierUserId)
            ->whereNotNull('restaurant_service_type')
            ->where('created_at', '>=', $session->opened_at)
            ->whereIn('status', ['draft', 'confirmed'])
            ->count();
    }

}
