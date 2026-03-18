<?php

declare(strict_types=1);

namespace App;

/**
 * Manages tenant permissions with granular control.
 * 
 * Permissions are grouped into predefined roles:
 * - owner: Same rights as admin, immutable label
 * - admin: Can manage members and OTP codes
 * - member: Can add and manage OTP codes
 * - viewer: Read-only access
 */
class PermissionManager
{
    // Permission definitions - maps roles to permissions
    private const ROLE_PERMISSIONS = [
        'owner' => [
            'can_view_tenant',
            'can_manage_members',
            'can_manage_roles',
            'can_edit_settings',
            'can_manage_otp',
            'can_create_otp',
            'can_export_otp',
            'can_edit_otp',
            'can_delete_otp',
        ],
        'admin' => [
            'can_view_tenant',
            'can_manage_members',
            'can_manage_roles',
            'can_edit_settings',
            'can_manage_otp',
            'can_create_otp',
            'can_export_otp',
            'can_edit_otp',
            'can_delete_otp',
        ],
        'member' => [
            'can_view_tenant',
            'can_manage_otp',
            'can_create_otp',
            'can_export_otp',
            'can_edit_otp',
            'can_delete_otp',
        ],
        'viewer' => [
            'can_view_tenant',
        ],
    ];

    // Valid roles that can be assigned to users
    private const VALID_ROLES = ['owner', 'admin', 'member', 'viewer'];

    /**
     * Get all valid roles.
     */
    public static function getValidRoles(): array
    {
        return self::VALID_ROLES;
    }

    /**
     * Get permissions for a given role.
     */
    public static function getPermissionsForRole(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Get role description in French.
     */
    public static function getRoleDescription(string $role): string
    {
        return match ($role) {
            'owner' => 'Propriétaire — mêmes droits que administrateur (non modifiable)',
            'admin' => 'Administrateur — gère membres, paramètres et OTP',
            'member' => 'Membre — peut créer, exporter, modifier et supprimer des OTP',
            'viewer' => 'Observateur — lecture seule',
            default => 'Rôle inconnu',
        };
    }

    /**
     * Check if a role has a specific permission.
     */
    public static function hasPermission(string $role, string $permission): bool
    {
        $permissions = self::getPermissionsForRole($role);
        return in_array($permission, $permissions, true);
    }

    /**
     * Check if a user with a given role can perform an action.
     * 
     * @param string $role The user's role in the tenant
     * @param string $action The action to check (e.g., 'manage_members')
     */
    public static function can(string $role, string $action): bool
    {
        $permissionKey = 'can_' . $action;
        return self::hasPermission($role, $permissionKey);
    }

    /**
     * Validate that a role is valid.
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::VALID_ROLES, true);
    }

    /**
     * Get role hierarchy (higher index = more permissions).
     */
    public static function getRoleHierarchy(string $role): int
    {
        return match ($role) {
            'owner' => 4,
            'admin' => 3,
            'member' => 2,
            'viewer' => 1,
            default => 0,
        };
    }

    /**
     * Check if a role can promote/demote another role.
     */
    public static function canManageRole(string $currentRole, string $targetRole): bool
    {
        // Can't demote someone to a higher hierarchy
        $currentHierarchy = self::getRoleHierarchy($currentRole);
        $targetHierarchy = self::getRoleHierarchy($targetRole);
        
        return $currentHierarchy > $targetHierarchy && self::can($currentRole, 'manage_roles');
    }
}
