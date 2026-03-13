<?php

declare(strict_types=1);

namespace App;

class Auth
{
    private PocketBaseClient $pb;
    private array $config;

    public function __construct(PocketBaseClient $pb, array $config)
    {
        $this->pb     = $pb;
        $this->config = $config;
    }

    public function isAuthenticated(): bool
    {
        return !empty($_SESSION['user']);
    }

    public function getCurrentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Local login via PocketBase users collection. */
    public function login(string $identity, string $password): bool
    {
        try {
            $result = $this->pb->authUser($identity, $password);
            $record = $result['record'] ?? null;
            if (!$record) {
                return false;
            }
            $this->setSession($record, 'local');
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /** OIDC SSO login — creates / updates PocketBase user automatically. */
    public function loginWithOidc(array $oidcUser): bool
    {
        if (empty($oidcUser['sub'])) {
            error_log('OIDC login failed: missing sub claim.');
            return false;
        }

        try {
            $this->ensureAdminToken();

            // Search by OIDC subject (stable unique identifier)
            $sub  = $this->escapeFilter($oidcUser['sub']);
            $list = $this->pb->listRecords('users', [
                'filter'  => "oidc_sub='{$sub}'",
                'perPage' => 1,
            ]);

            if (!empty($list['items'])) {
                $record = $list['items'][0];
                // Sync name and email if changed
                $this->pb->updateRecord('users', $record['id'], [
                    'name'  => $oidcUser['name'],
                    'email' => $oidcUser['email'] ?: $record['email'],
                ]);
                $record['name']  = $oidcUser['name'];
                $record['email'] = $oidcUser['email'] ?: $record['email'];
            } else {
                // First login — provision user
                $randomPw = bin2hex(random_bytes(20));
                $email    = $oidcUser['email'] ?: ($oidcUser['username'] . '@oidc.local');
                $record   = $this->pb->createRecord('users', [
                    'email'           => $email,
                    'password'        => $randomPw,
                    'passwordConfirm' => $randomPw,
                    'name'            => $oidcUser['name'] ?: $oidcUser['username'],
                    'oidc_sub'        => $oidcUser['sub'],
                    'is_oidc_user'    => true,
                    'is_app_admin'    => false,
                ]);
            }

            $this->setSession($record, 'oidc');
            return true;
        } catch (\Exception $e) {
            error_log('OIDC PocketBase sync error: ' . $e->getMessage());
            return false;
        }
    }

    /** LDAP login — creates / updates PocketBase user automatically. */
    public function loginWithLdap(array $ldapUser): bool
    {
        try {
            $this->ensureAdminToken();

            // Search for existing PocketBase user with matching AD username
            $list = $this->pb->listRecords('users', [
                'filter' => "ad_username='" . $this->escapeFilter($ldapUser['username']) . "'",
                'perPage' => 1,
            ]);

            if (!empty($list['items'])) {
                $record = $list['items'][0];
                // Update name / email if changed
                $this->pb->updateRecord('users', $record['id'], [
                    'name'  => $ldapUser['name'],
                    'email' => $ldapUser['email'],
                ]);
                $record['name']  = $ldapUser['name'];
                $record['email'] = $ldapUser['email'];
            } else {
                // Create new user
                $randomPw = bin2hex(random_bytes(20));
                $record   = $this->pb->createRecord('users', [
                    'email'           => $ldapUser['email'],
                    'password'        => $randomPw,
                    'passwordConfirm' => $randomPw,
                    'name'            => $ldapUser['name'],
                    'ad_username'     => $ldapUser['username'],
                    'is_ad_user'      => true,
                    'is_app_admin'    => false,
                ]);
            }

            $this->setSession($record, 'ldap');
            return true;
        } catch (\Exception $e) {
            error_log('LDAP PocketBase sync error: ' . $e->getMessage());
            return false;
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
    }

    // ── helpers ─────────────────────────────────────────────────

    private function setSession(array $record, string $authType): void
    {
        $_SESSION['user'] = [
            'id'             => $record['id'],
            'email'          => $record['email'] ?? '',
            'name'           => $record['name'] ?? '',
            'is_app_admin'   => !empty($record['is_app_admin']),
            'is_ad_user'     => !empty($record['is_ad_user']),
            'is_oidc_user'   => !empty($record['is_oidc_user']),
            'auth_type'      => $authType,
        ];
    }

    public function ensureAdminToken(): void
    {
        if (!empty($_SESSION['pb_admin_token'])) {
            $this->pb->setToken($_SESSION['pb_admin_token']);
            return;
        }
        $result = $this->pb->authAdmin(
            $this->config['pocketbase']['admin_email'],
            $this->config['pocketbase']['admin_password'],
        );
        $_SESSION['pb_admin_token'] = $result['token'];
        $this->pb->setToken($result['token']);
    }

    private function escapeFilter(string $value): string
    {
        return str_replace(["'", '"', '\\'], '', $value);
    }

    // ── Permission helpers ───────────────────────────────────────

    /**
     * Check if current user has permission in a tenant.
     */
    public function canDoInTenant(string $tenantId, string $action): bool
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return false;
        }

        // App admins can do everything
        if ($currentUser['is_app_admin'] ?? false) {
            return true;
        }

        // Check tenant membership and role
        $tm = new TenantManager($this->pb);
        $membership = $tm->getMembership($tenantId, $currentUser['id']);
        
        if (!$membership) {
            return false;
        }

        $role = $membership['role'] ?? null;
        if (!$role) {
            return false;
        }

        return PermissionManager::can($role, $action);
    }

    /**
     * Get user's role in a tenant.
     */
    public function getUserTenantRole(string $tenantId): ?string
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            return null;
        }

        // App admins default to owner-like permissions
        if ($currentUser['is_app_admin'] ?? false) {
            return 'owner';
        }

        $tm = new TenantManager($this->pb);
        $membership = $tm->getMembership($tenantId, $currentUser['id']);
        
        return $membership['role'] ?? null;
    }
}
