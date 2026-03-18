<?php

declare(strict_types=1);

use App\PermissionManager;

return [
    [
        'name' => 'viewer cannot export/edit/delete otp',
        'test' => static function (): void {
            assertFalse(PermissionManager::can('viewer', 'export_otp'), 'viewer should not export otp');
            assertFalse(PermissionManager::can('viewer', 'edit_otp'), 'viewer should not edit otp');
            assertFalse(PermissionManager::can('viewer', 'delete_otp'), 'viewer should not delete otp');
        },
    ],
    [
        'name' => 'member can export/edit/delete otp',
        'test' => static function (): void {
            assertTrue(PermissionManager::can('member', 'export_otp'), 'member should export otp');
            assertTrue(PermissionManager::can('member', 'edit_otp'), 'member should edit otp');
            assertTrue(PermissionManager::can('member', 'delete_otp'), 'member should delete otp');
        },
    ],
];
