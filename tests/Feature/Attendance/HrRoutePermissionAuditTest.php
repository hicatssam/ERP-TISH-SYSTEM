<?php

namespace Tests\Feature\Attendance;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Permission guard contract across the HR suite. Existing workflow
 * tests verify branch-level record ownership and the business rules;
 * these assertions catch accidental removal of route-level guards.
 */
class HrRoutePermissionAuditTest extends TestCase
{
    #[Test]
    public function sensitive_hr_admin_actions_require_their_specific_permission(): void
    {
        $routes = [
            'employees.index' => 'employees.manage',
            'employees.create' => 'employees.manage',
            'employees.store' => 'employees.manage',
            'employees.edit' => 'employees.manage',
            'employees.update' => 'employees.manage',
            'employees.destroy' => 'employees.manage',
            'employees.toggle' => 'employees.manage',
            'employees.create-user' => 'users.create',
            'hr.dashboard' => 'hr.dashboard.view',
            'hr.report.csv' => 'hr.dashboard.view',
            'hr.organization.index' => 'hr.organization.view',
            'hr.organization.csv' => 'hr.organization.view',
            'hr.departments.store' => 'hr.organization.manage',
            'hr.positions.store' => 'hr.organization.manage',
            'hr.cost-centers.store' => 'hr.organization.manage',
            'hr.assignments.store' => 'hr.organization.manage',
            'hr.employees.file' => 'hr.documents.view',
            'hr.employees.file.update' => 'hr.documents.manage',
            'hr.employees.documents.store' => 'hr.documents.manage',
            'hr.employees.documents.download' => 'hr.documents.view',
            'attendance.index' => 'attendance.view',
            'attendance.store' => 'attendance.manage',
            'attendance.approve' => 'attendance.approve',
            'attendance.self-requests.index' => 'attendance.approve',
            'attendance.self-requests.approve' => 'attendance.approve',
            'attendance.self-requests.reject' => 'attendance.approve',
            'attendance.shifts.index' => 'attendance.view',
            'attendance.shifts.store' => 'attendance.shifts.manage',
            'attendance.shifts.assign' => 'attendance.shifts.manage',
            'attendance.leaves.index' => 'attendance.leaves.view',
            'attendance.leaves.store' => 'attendance.leaves.manage',
            'attendance.leaves.carryover' => 'attendance.leaves.manage',
            'attendance.leaves.approve' => 'attendance.leaves.approve',
            'attendance.leaves.reject' => 'attendance.leaves.approve',
            'attendance.corrections.index' => 'attendance.approve',
            'attendance.corrections.approve' => 'attendance.approve',
            'attendance.corrections.reject' => 'attendance.approve',
            'attendance.leave-types.index' => 'settings.manage',
            'attendance.leave-types.store' => 'settings.manage',
            'attendance.leave-types.update' => 'settings.manage',
            'attendance.holidays.index' => 'settings.manage',
            'attendance.holidays.store' => 'settings.manage',
            'attendance.holidays.destroy' => 'settings.manage',
            'payroll.index' => 'payroll.view',
            'payroll.periods.store' => 'payroll.manage',
            'payroll.show' => 'payroll.view',
            'payroll.calculate' => 'payroll.manage',
            'payroll.approve' => 'payroll.approve',
            'payroll.pay' => 'payroll.pay',
            'payroll.payslip' => 'payroll.documents.print',
            'payroll.payments.receipt' => 'payroll.documents.print',
            'payroll.payments.verify' => 'payroll.payments.verify',
            'payroll.payments.reject' => 'payroll.payments.verify',
            'payroll.payments.void' => 'payroll.payments.void',
            'payroll.employees.show' => 'employee_ledger.view',
            'payroll.employees.compensation' => 'payroll.manage',
            'payroll.employees.adjustments' => 'payroll.adjustments.manage',
            'payroll.employees.advances' => 'payroll.advances.manage',
            'payroll.employees.advances.repayments.store' => 'payroll.advances.manage',
            'payroll.advance-repayments.verify' => 'payroll.payments.verify',
            'payroll.advance-repayments.reject' => 'payroll.payments.verify',
            'payroll.employees.statement.print' => 'payroll.documents.print',
            'payroll.reports.index' => 'payroll.reports.view',
            'payroll.reports.csv' => 'payroll.reports.view',
        ];

        foreach ($routes as $name => $permission) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing HR route: {$name}");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "Unprotected HR route: {$name}");
            $this->assertContains('location.scope', $middleware, "Unscoped HR route: {$name}");
            $this->assertContains('can:'.$permission, $middleware, "Missing action permission on {$name}");
        }

        $this->assertGreaterThanOrEqual(60, count($routes));
    }

    #[Test]
    public function employee_self_service_requires_login_and_attendance_guards(): void
    {
        foreach ([
            'my-hr.index',
            'my-hr.payslips.show',
            'my-hr.leaves.store',
            'my-hr.corrections.store',
            'my-hr.punch',
            'my-hr.webauthn.registration-options',
            'my-hr.webauthn.register',
            'my-hr.webauthn.punch-options',
            'my-hr.webauthn.punch',
            'my-hr.face.challenge',
            'my-hr.face.punch',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing employee portal route: {$name}");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "Employee private endpoint without auth: {$name}");
            $this->assertContains('location.scope', $middleware, "Employee endpoint lacks location scope: {$name}");
            $this->assertContains('password.changed', $middleware, "Employee endpoint lacks password policy: {$name}");
        }

        foreach (['my-hr.leaves.store', 'my-hr.corrections.store', 'my-hr.punch'] as $name) {
            $this->assertContains(
                \App\Http\Middleware\EnsureAttendanceEnabled::class,
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Attendance feature toggle bypass at {$name}"
            );
        }

        foreach ([
            'my-hr.webauthn.register', 'my-hr.webauthn.punch',
            'my-hr.face.challenge', 'my-hr.face.punch',
        ] as $name) {
            $this->assertContains(
                \App\Http\Middleware\EnsureBiometricAttendanceEnabled::class,
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                "Biometric feature toggle bypass at {$name}"
            );
        }
    }
}
