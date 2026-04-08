<?php

declare(strict_types=1);

namespace App;

class Setup
{
    private PocketBaseClient $pb;
    private array $config;

    public function __construct(PocketBaseClient $pb, array $config)
    {
        $this->pb     = $pb;
        $this->config = $config;
    }

    /** Check whether PocketBase has been set up with our collections. */
    public function isInitialized(): bool
    {
        try {
            $result = $this->pb->authAdmin(
                $this->config['pocketbase']['admin_email'],
                $this->config['pocketbase']['admin_password'],
            );
            $this->pb->setToken($result['token']);
            $_SESSION['pb_admin_token'] = $result['token'];

            $col = $this->pb->getCollection('otp_codes');
            if ($col) {
                // Ensure schema stays compatible after upgrades.
                $this->extendUsersCollection();
                $this->ensureAuditCollections();
                $this->ensureRuntimeSettingsCollection();
                $this->createCollectionIfMissing('otp_groups', [
                    ['name' => 'name',       'type' => 'text',     'required' => true,  'min' => 1, 'max' => 120],
                    ['name' => 'tenant',     'type' => 'relation', 'required' => true,  'collectionId' => $this->getCollectionId('tenants'), 'maxSelect' => 1, 'cascadeDelete' => true],
                    ['name' => 'created_by', 'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
                ]);
                $this->createCollectionIfMissing('otp_favorites', [
                    ['name' => 'user', 'type' => 'relation', 'required' => true, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => true],
                    ['name' => 'otp',  'type' => 'relation', 'required' => true, 'collectionId' => $this->getCollectionId('otp_codes'), 'maxSelect' => 1, 'cascadeDelete' => true],
                ]);
                $this->migrateTenantsCollection();
                $this->migrateTenantMembersCollection();
                $this->migrateOtpCodesCollection();
                $this->migrateOtpFavoritesCollection();
                $_SESSION['pb_initialized'] = true;
                return true;
            }
            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /** Run full initialization: admin, collections, first user. */
    public function initialize(): void
    {
        // 1. Authenticate as admin (superuser created by entrypoint)
        $result = $this->pb->authAdmin(
            $this->config['pocketbase']['admin_email'],
            $this->config['pocketbase']['admin_password'],
        );
        $this->pb->setToken($result['token']);
        $_SESSION['pb_admin_token'] = $result['token'];

        // 2. Extend users collection
        $this->extendUsersCollection();
        $this->ensureAuditCollections();
        $this->ensureRuntimeSettingsCollection();

        // 3. Create custom collections
        $this->createCollectionIfMissing('tenants', [
            ['name' => 'name',        'type' => 'text',     'required' => true,  'min' => 1, 'max' => 200],
            ['name' => 'description',  'type' => 'text',     'required' => false],
            ['name' => 'created_by',   'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'deleted',      'type' => 'bool',     'required' => false],
            ['name' => 'deleted_by',   'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'deleted_at',   'type' => 'text',     'required' => false],
        ]);

        $this->migrateTenantsCollection();

        $this->createCollectionIfMissing('tenant_members', [
            ['name' => 'tenant', 'type' => 'relation', 'required' => true,  'collectionId' => $this->getCollectionId('tenants'), 'maxSelect' => 1, 'cascadeDelete' => true],
            ['name' => 'user',   'type' => 'relation', 'required' => true,  'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => true],
            ['name' => 'role',   'type' => 'select',   'required' => true,  'values' => ['owner', 'admin', 'member', 'viewer'], 'maxSelect' => 1],
            ['name' => 'status', 'type' => 'select',   'required' => false, 'values' => ['active', 'pending', 'disabled'], 'maxSelect' => 1],
        ]);

        $this->migrateTenantMembersCollection();

        $this->createCollectionIfMissing('otp_groups', [
            ['name' => 'name',       'type' => 'text',     'required' => true,  'min' => 1, 'max' => 120],
            ['name' => 'tenant',     'type' => 'relation', 'required' => true,  'collectionId' => $this->getCollectionId('tenants'), 'maxSelect' => 1, 'cascadeDelete' => true],
            ['name' => 'created_by', 'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
        ]);

        $this->createCollectionIfMissing('otp_codes', [
            ['name' => 'name',        'type' => 'text',     'required' => true,  'min' => 1, 'max' => 200],
            ['name' => 'issuer',      'type' => 'text',     'required' => false],
            ['name' => 'secret_enc',  'type' => 'text',     'required' => true],
            ['name' => 'algorithm',   'type' => 'select',   'required' => false, 'values' => ['SHA1', 'SHA256', 'SHA512'], 'maxSelect' => 1],
            ['name' => 'digits',      'type' => 'number',   'required' => false, 'min' => 6, 'max' => 8],
            ['name' => 'period',      'type' => 'number',   'required' => false, 'min' => 15, 'max' => 120],
            ['name' => 'type',        'type' => 'select',   'required' => false, 'values' => ['totp', 'hotp'], 'maxSelect' => 1],
            ['name' => 'counter',     'type' => 'number',   'required' => false],
            ['name' => 'owner',       'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'tenant',      'type' => 'relation', 'required' => false, 'collectionId' => $this->getCollectionId('tenants'), 'maxSelect' => 1, 'cascadeDelete' => true],
            ['name' => 'group',       'type' => 'relation', 'required' => false, 'collectionId' => $this->getCollectionId('otp_groups'), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'is_personal', 'type' => 'bool',     'required' => false],
            ['name' => 'deleted',     'type' => 'bool',     'required' => false],
            ['name' => 'deleted_by',  'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'deleted_at',  'type' => 'text',     'required' => false],
        ]);

        $this->createCollectionIfMissing('otp_favorites', [
            ['name' => 'user', 'type' => 'relation', 'required' => true, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => true],
            ['name' => 'otp',  'type' => 'relation', 'required' => true, 'collectionId' => $this->getCollectionId('otp_codes'), 'maxSelect' => 1, 'cascadeDelete' => true],
        ]);

        $this->migrateOtpCodesCollection();
        $this->migrateOtpFavoritesCollection();

        // 4. Create default app user
        $this->createDefaultUser();

        $_SESSION['pb_initialized'] = true;
    }

    // ── helpers ─────────────────────────────────────────────────

    private function extendUsersCollection(): void
    {
        $users = $this->pb->getCollection('users');
        if (!$users) {
            return;
        }

        $fields   = $users['fields'] ?? [];
        $existing = array_column($fields, 'name');

        $additions = [];
        if (!in_array('oidc_sub', $existing, true)) {
            $additions[] = ['name' => 'oidc_sub', 'type' => 'text', 'required' => false];
        }
        if (!in_array('is_oidc_user', $existing, true)) {
            $additions[] = ['name' => 'is_oidc_user', 'type' => 'bool', 'required' => false];
        }
        if (!in_array('ad_username', $existing, true)) {
            $additions[] = ['name' => 'ad_username', 'type' => 'text', 'required' => false];
        }
        if (!in_array('is_ad_user', $existing, true)) {
            $additions[] = ['name' => 'is_ad_user', 'type' => 'bool', 'required' => false];
        }
        if (!in_array('is_app_admin', $existing, true)) {
            $additions[] = ['name' => 'is_app_admin', 'type' => 'bool', 'required' => false];
        }
        if (!in_array('allow_personal_otp', $existing, true)) {
            $additions[] = ['name' => 'allow_personal_otp', 'type' => 'bool', 'required' => false];
        }
        if ($additions) {
            $this->pb->updateCollection($users['id'], [
                'fields' => array_merge($fields, $additions),
            ]);
        }
    }

    private function createCollectionIfMissing(string $name, array $fields): void
    {
        if ($this->pb->getCollection($name)) {
            return;
        }
        $this->pb->createCollection([
            'name'       => $name,
            'type'       => 'base',
            'fields'     => $fields,
            'listRule'   => null,
            'viewRule'   => null,
            'createRule' => null,
            'updateRule' => null,
            'deleteRule' => null,
        ]);
    }

    private function ensureAuditCollections(): void
    {
        $this->createCollectionIfMissing('audit_logs', [
            ['name' => 'category',   'type' => 'text',     'required' => true, 'min' => 1, 'max' => 40],
            ['name' => 'action',     'type' => 'text',     'required' => true, 'min' => 1, 'max' => 80],
            ['name' => 'actor',      'type' => 'relation', 'required' => false, 'collectionId' => $this->getUsersCollectionId(), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'actor_name', 'type' => 'text',     'required' => false, 'max' => 200],
            ['name' => 'target_id',  'type' => 'text',     'required' => false, 'max' => 60],
            ['name' => 'target_name','type' => 'text',     'required' => false, 'max' => 255],
            ['name' => 'tenant',     'type' => 'relation', 'required' => false, 'collectionId' => $this->getCollectionId('tenants'), 'maxSelect' => 1, 'cascadeDelete' => false],
            ['name' => 'details',    'type' => 'text',     'required' => false],
            ['name' => 'ip',         'type' => 'text',     'required' => false, 'max' => 100],
            ['name' => 'logged_at',  'type' => 'text',     'required' => true, 'max' => 40],
        ]);

        $this->createCollectionIfMissing('auth_failures', [
            ['name' => 'identity',   'type' => 'text', 'required' => false, 'max' => 255],
            ['name' => 'login_type', 'type' => 'text', 'required' => false, 'max' => 40],
            ['name' => 'reason',     'type' => 'text', 'required' => false, 'max' => 255],
            ['name' => 'ip',         'type' => 'text', 'required' => false, 'max' => 100],
            ['name' => 'user_agent', 'type' => 'text', 'required' => false],
            ['name' => 'occurred_at','type' => 'text', 'required' => true, 'max' => 40],
        ]);
    }

    private function ensureRuntimeSettingsCollection(): void
    {
        $this->createCollectionIfMissing('app_runtime_settings', [
            ['name' => 'key',   'type' => 'text', 'required' => true, 'min' => 1, 'max' => 120],
            ['name' => 'value', 'type' => 'text', 'required' => true],
        ]);
    }

    private function getUsersCollectionId(): string
    {
        return $this->getCollectionId('users');
    }

    private function getCollectionId(string $name): string
    {
        static $cache = [];
        if (isset($cache[$name])) {
            return $cache[$name];
        }
        $col = $this->pb->getCollection($name);
        if ($col) {
            $cache[$name] = $col['id'];
            return $col['id'];
        }
        return $name;
    }

    private function createDefaultUser(): void
    {
        $result = $this->pb->listRecords('users', ['perPage' => 1]);
        if (($result['totalItems'] ?? 0) > 0) {
            return; // users already exist
        }

        $admin = $this->config['default_admin'];

        $this->pb->createRecord('users', [
            'email'           => $admin['email'],
            'password'        => $admin['password'],
            'passwordConfirm' => $admin['password'],
            'name'            => $admin['name'],
            'is_app_admin'    => true,
            'allow_personal_otp' => false,
            'is_ad_user'      => false,
            'ad_username'     => '',
        ]);
    }

    private function migrateTenantMembersCollection(): void
    {
        $collection = $this->pb->getCollection('tenant_members');
        if (!$collection) {
            return;
        }

        $fields = $collection['fields'] ?? [];
        if (!$fields) {
            return;
        }

        $changed = false;
        $hasStatus = false;

        foreach ($fields as &$field) {
            if (($field['name'] ?? '') === 'role' && ($field['type'] ?? '') === 'select') {
                $desired = ['owner', 'admin', 'member', 'viewer'];
                $current = $field['values'] ?? [];
                if ($current !== $desired) {
                    $field['values'] = $desired;
                    $field['maxSelect'] = 1;
                    $changed = true;
                }
            }

            if (($field['name'] ?? '') === 'status') {
                $hasStatus = true;
                if (($field['type'] ?? '') === 'select') {
                    $desiredStatus = ['active', 'pending', 'disabled'];
                    $currentStatus = $field['values'] ?? [];
                    if ($currentStatus !== $desiredStatus) {
                        $field['values'] = $desiredStatus;
                        $field['maxSelect'] = 1;
                        $changed = true;
                    }
                }
            }
        }
        unset($field);

        if (!$hasStatus) {
            $fields[] = [
                'name' => 'status',
                'type' => 'select',
                'required' => false,
                'values' => ['active', 'pending', 'disabled'],
                'maxSelect' => 1,
            ];
            $changed = true;
        }

        if ($changed) {
            $this->pb->updateCollection($collection['id'], ['fields' => $fields]);
        }

        // Migrate any existing 'editor' members to 'member'
        try {
            $editorRecords = $this->pb->listRecords('tenant_members', [
                'filter' => 'role = "editor"',
                'perPage' => 200,
            ]);
            foreach (($editorRecords['items'] ?? []) as $m) {
                $this->pb->updateRecord('tenant_members', $m['id'], ['role' => 'member']);
            }
        } catch (\Exception) {
            // Ignore if already clean or API error
        }
    }

    private function migrateTenantsCollection(): void
    {
        $collection = $this->pb->getCollection('tenants');
        if (!$collection) {
            return;
        }

        $fields = $collection['fields'] ?? [];
        if (!$fields) {
            return;
        }

        $existing = array_column($fields, 'name');
        $changed = false;

        if (!in_array('deleted', $existing, true)) {
            $fields[] = ['name' => 'deleted', 'type' => 'bool', 'required' => false];
            $changed = true;
        }
        if (!in_array('deleted_by', $existing, true)) {
            $fields[] = [
                'name' => 'deleted_by',
                'type' => 'relation',
                'required' => false,
                'collectionId' => $this->getUsersCollectionId(),
                'maxSelect' => 1,
                'cascadeDelete' => false,
            ];
            $changed = true;
        }
        if (!in_array('deleted_at', $existing, true)) {
            $fields[] = ['name' => 'deleted_at', 'type' => 'text', 'required' => false];
            $changed = true;
        }
        if ($changed) {
            $this->pb->updateCollection($collection['id'], ['fields' => $fields]);
        }
    }

    private function migrateOtpCodesCollection(): void
    {
        $collection = $this->pb->getCollection('otp_codes');
        if (!$collection) {
            return;
        }

        $fields = $collection['fields'] ?? [];
        if (!$fields) {
            return;
        }

        $existing = array_column($fields, 'name');
        $changed = false;

        if (!in_array('deleted', $existing, true)) {
            $fields[] = ['name' => 'deleted', 'type' => 'bool', 'required' => false];
            $changed = true;
        }
        if (!in_array('deleted_by', $existing, true)) {
            $fields[] = [
                'name' => 'deleted_by',
                'type' => 'relation',
                'required' => false,
                'collectionId' => $this->getUsersCollectionId(),
                'maxSelect' => 1,
                'cascadeDelete' => false,
            ];
            $changed = true;
        }
        if (!in_array('deleted_at', $existing, true)) {
            $fields[] = ['name' => 'deleted_at', 'type' => 'text', 'required' => false];
            $changed = true;
        }
        if (!in_array('group', $existing, true)) {
            $fields[] = [
                'name' => 'group',
                'type' => 'relation',
                'required' => false,
                'collectionId' => $this->getCollectionId('otp_groups'),
                'maxSelect' => 1,
                'cascadeDelete' => false,
            ];
            $changed = true;
        }

        if ($changed) {
            $this->pb->updateCollection($collection['id'], ['fields' => $fields]);
        }
    }

    private function migrateOtpFavoritesCollection(): void
    {
        $collection = $this->pb->getCollection('otp_favorites');
        if (!$collection) {
            return;
        }

        $fields = $collection['fields'] ?? [];
        if (!$fields) {
            return;
        }

        $existing = array_column($fields, 'name');
        $changed = false;

        if (!in_array('user', $existing, true)) {
            $fields[] = [
                'name' => 'user',
                'type' => 'relation',
                'required' => true,
                'collectionId' => $this->getUsersCollectionId(),
                'maxSelect' => 1,
                'cascadeDelete' => true,
            ];
            $changed = true;
        }

        if (!in_array('otp', $existing, true)) {
            $fields[] = [
                'name' => 'otp',
                'type' => 'relation',
                'required' => true,
                'collectionId' => $this->getCollectionId('otp_codes'),
                'maxSelect' => 1,
                'cascadeDelete' => true,
            ];
            $changed = true;
        }

        if ($changed) {
            $this->pb->updateCollection($collection['id'], ['fields' => $fields]);
        }
    }
}
