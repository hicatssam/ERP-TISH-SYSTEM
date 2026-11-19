<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

class CashSessionPolicy
{
    public function manage(User $user): bool
    {
        return $user->checkPermissionTo('cash_sessions.manage', 'web') || $user->isAdmin();
    }

    public function open(User $user): bool
    {
        return $user->checkPermissionTo('cash_sessions.manage', 'web') || $user->isAdmin();
    }

    public function close(User $user, CashSession $session): bool
    {
        // Only the employee who opened it, their manager, or admin can close
        return $user->isAdmin()
            || $user->checkPermissionTo('cash_sessions.manage', 'web')
            || $user->employee?->id === $session->employee_id;
    }

    public function view(User $user, CashSession $session): bool
    {
        return $user->isAdmin()
            || $user->checkPermissionTo('cash_sessions.manage', 'web')
            || $user->employee?->id === $session->employee_id
            || $session->location_id === $user->primaryLocation()?->id;
    }
}
