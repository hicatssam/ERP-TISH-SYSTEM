<?php

namespace App\Support\Authorization;

use App\Support\PermissionUi;

/**
 * Backwards-compatible adapter for the single permission/role translation
 * catalog. The UI and authorization reporting must never disagree.
 */
final class PermissionLabels
{
    /** @return array<string, string> */
    public static function groupLabels(): array
    {
        return PermissionUi::groupLabels();
    }

    /** @return array<string, string> */
    public static function permissionLabels(): array
    {
        return PermissionUi::permissionLabels();
    }

    /** @return array<string, string> */
    public static function roleLabels(): array
    {
        return PermissionUi::roleLabels();
    }

    public static function group(string $permission): string
    {
        return explode('.', $permission)[0];
    }

    public static function groupLabel(string $group): string
    {
        return PermissionUi::groupLabel($group);
    }

    public static function label(string $permission): string
    {
        return PermissionUi::permissionLabel($permission);
    }

    public static function roleLabel(string $role): string
    {
        return PermissionUi::roleLabel($role);
    }
}
