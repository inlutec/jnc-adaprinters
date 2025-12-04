<?php

namespace App\Helpers;

use App\Models\User;

class PermissionHelper
{
    /**
     * Verifica si el usuario tiene acceso a una página específica
     * Si no tiene page_permissions definido, tiene acceso a todas las páginas
     */
    public static function canAccessPage(User $user, string $pageName): bool
    {
        if (!$user->page_permissions || count($user->page_permissions) === 0) {
            return true; // Sin restricciones = acceso a todo
        }
        return in_array($pageName, $user->page_permissions);
    }

    /**
     * Verifica si el usuario puede ver una provincia específica
     * Si no tiene location_permissions definido, puede ver todas
     */
    public static function canViewProvince(User $user, int $provinceId): bool
    {
        if (!$user->location_permissions) {
            return true; // Sin restricciones = ver todo
        }
        if (!isset($user->location_permissions['provinces']) || count($user->location_permissions['provinces']) === 0) {
            return true;
        }
        return in_array($provinceId, $user->location_permissions['provinces']);
    }

    /**
     * Verifica si el usuario puede ver una sede específica
     */
    public static function canViewSite(User $user, int $siteId): bool
    {
        if (!$user->location_permissions) {
            return true;
        }
        if (!isset($user->location_permissions['sites']) || count($user->location_permissions['sites']) === 0) {
            return true;
        }
        return in_array($siteId, $user->location_permissions['sites']);
    }

    /**
     * Verifica si el usuario puede ver un departamento específico
     */
    public static function canViewDepartment(User $user, int $departmentId): bool
    {
        if (!$user->location_permissions) {
            return true;
        }
        if (!isset($user->location_permissions['departments']) || count($user->location_permissions['departments']) === 0) {
            return true;
        }
        return in_array($departmentId, $user->location_permissions['departments']);
    }

    /**
     * Verifica si el usuario tiene permisos de escritura en un módulo
     * Si no tiene read_write_permissions definido, solo tiene lectura
     */
    public static function canWrite(User $user, string $module): bool
    {
        if (!$user->read_write_permissions || count($user->read_write_permissions) === 0) {
            return false; // Sin permisos de escritura = solo lectura
        }
        return in_array($module, $user->read_write_permissions);
    }
}

