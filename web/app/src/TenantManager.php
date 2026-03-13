<?php

declare(strict_types=1);

namespace App;

class TenantManager
{
    private PocketBaseClient $pb;

    public function __construct(PocketBaseClient $pb)
    {
        $this->pb = $pb;
    }

    // ── Tenants ─────────────────────────────────────────────────

    public function create(string $name, string $description, string $createdBy): array
    {
        $tenant = $this->pb->createRecord('tenants', [
            'name'        => $name,
            'description' => $description,
            'created_by'  => $createdBy,
        ]);

        // Auto-add creator as owner
        $this->addMember($tenant['id'], $createdBy, 'owner', 'active');

        return $tenant;
    }

    public function getById(string $id): ?array
    {
        return $this->pb->getRecord('tenants', $id);
    }

    public function update(string $id, array $data): array
    {
        return $this->pb->updateRecord('tenants', $id, $data);
    }

    public function delete(string $id): bool
    {
        return $this->pb->deleteRecord('tenants', $id);
    }

    public function getUserTenants(string $userId): array
    {
        $memberships = $this->pb->listRecords('tenant_members', [
            'filter'  => "user='{$this->esc($userId)}'",
            'expand'  => 'tenant',
            'perPage' => 100,
        ]);

        $tenants = [];
        foreach ($memberships['items'] ?? [] as $m) {
            $tenant = $m['expand']['tenant'] ?? null;
            if ($tenant) {
                $tenant['_role'] = $m['role'];
                $tenant['_membership_id'] = $m['id'];
                $tenants[] = $tenant;
            }
        }
        return $tenants;
    }

    // ── Members ─────────────────────────────────────────────────

    /** Add a member to a tenant. */
    public function addMember(string $tenantId, string $userId, string $role = 'member', string $status = 'active'): array
    {
        return $this->pb->createRecord('tenant_members', [
            'tenant'  => $tenantId,
            'user'    => $userId,
            'role'    => $role,
            'status'  => $status,
        ]);
    }

    /**
     * Add a member by email.
     * The user must already exist.
     */
    public function addMemberByEmail(string $tenantId, string $email, string $role = 'member'): array
    {
        $email = trim(strtolower($email));

        $user = $this->findUserByEmail($email);

        if (!$user) {
            throw new \Exception('Utilisateur introuvable. Seuls les utilisateurs existants peuvent etre ajoutes.');
        }

        return $this->addMemberById($tenantId, $user['id'], $role);
    }

    /**
     * Add a member by user id.
     */
    public function addMemberById(string $tenantId, string $userId, string $role = 'member'): array
    {
        $user = $this->pb->getRecord('users', $userId);
        if (!$user) {
            throw new \Exception('Utilisateur introuvable.');
        }

        $existing = $this->getMembership($tenantId, $user['id']);
        if ($existing) {
            throw new \Exception('Cet utilisateur est déjà membre.');
        }

        return $this->addMember($tenantId, $user['id'], $role, 'active');
    }

    /**
     * Add multiple members at once.
     * 
     * @param string $tenantId
     * @param array $members Array of ['email' => 'user@example.com', 'role' => 'member']
     */
    public function addMembersInBulk(string $tenantId, array $members): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($members as $member) {
            $email = trim($member['email'] ?? '');
            $role = $member['role'] ?? 'member';

            if (!$email) {
                $results['failed'][] = ['email' => '', 'error' => 'Email vide'];
                continue;
            }

            try {
                $result = $this->addMemberByEmail($tenantId, $email, $role);
                $results['success'][] = $result;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function getMembershipById(string $membershipId): ?array
    {
        return $this->pb->getRecord('tenant_members', $membershipId);
    }

    public function updateMemberRole(string $membershipId, string $role): array
    {
        return $this->pb->updateRecord('tenant_members', $membershipId, [
            'role' => $role,
        ]);
    }

    public function updateMemberStatus(string $membershipId, string $status): array
    {
        return $this->pb->updateRecord('tenant_members', $membershipId, [
            'status' => $status,
        ]);
    }

    public function removeMember(string $membershipId): bool
    {
        return $this->pb->deleteRecord('tenant_members', $membershipId);
    }

    public function getMembers(string $tenantId): array
    {
        $result = $this->pb->listRecords('tenant_members', [
            'filter'  => "tenant='{$this->esc($tenantId)}'",
            'expand'  => 'user',
            'perPage' => 200,
        ]);
        return $result['items'] ?? [];
    }

    public function getUserRole(string $tenantId, string $userId): ?string
    {
        $result = $this->pb->listRecords('tenant_members', [
            'filter'  => "tenant='{$this->esc($tenantId)}' && user='{$this->esc($userId)}'",
            'perPage' => 1,
        ]);
        return $result['items'][0]['role'] ?? null;
    }

    public function getMembership(string $tenantId, string $userId): ?array
    {
        $result = $this->pb->listRecords('tenant_members', [
            'filter'  => "tenant='{$this->esc($tenantId)}' && user='{$this->esc($userId)}'",
            'perPage' => 1,
        ]);
        return $result['items'][0] ?? null;
    }

    // ── Users search (for adding members) ───────────────────────

    public function searchUsers(string $query): array
    {
        $result = $this->pb->listRecords('users', [
            'filter'  => "name~'{$this->esc($query)}' || email~'{$this->esc($query)}'",
            'perPage' => 20,
        ]);
        return $result['items'] ?? [];
    }

    public function getAllUsers(): array
    {
        $result = $this->pb->listRecords('users', ['perPage' => 200]);
        return $result['items'] ?? [];
    }

    /**
     * Returns users that are not already members of the tenant.
     */
    public function getAddableUsers(string $tenantId): array
    {
        $allUsers = $this->getAllUsers();
        $members = $this->getMembers($tenantId);
        $memberUserIds = [];

        foreach ($members as $member) {
            $uid = $member['user'] ?? '';
            if ($uid !== '') {
                $memberUserIds[$uid] = true;
            }
        }

        $addable = [];
        foreach ($allUsers as $user) {
            $uid = $user['id'] ?? '';
            if ($uid !== '' && !isset($memberUserIds[$uid])) {
                $addable[] = $user;
            }
        }

        usort($addable, static function (array $a, array $b): int {
            $aKey = strtolower((string)($a['name'] ?? $a['email'] ?? ''));
            $bKey = strtolower((string)($b['name'] ?? $b['email'] ?? ''));
            return $aKey <=> $bKey;
        });

        return $addable;
    }

    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $list = $this->pb->listRecords('users', [
            'filter'  => "email='{$this->esc($email)}'",
            'perPage' => 1,
        ]);
        return $list['items'][0] ?? null;
    }

    /**
     * List all tenants.
     */
    public function getAllTenants(): array
    {
        $result = $this->pb->listRecords('tenants', ['perPage' => 200]);
        return $result['items'] ?? [];
    }

    /**
     * List memberships for a user, expanded with tenant info.
     */
    public function getUserMemberships(string $userId): array
    {
        $result = $this->pb->listRecords('tenant_members', [
            'filter'  => "user='{$this->esc($userId)}'",
            'expand'  => 'tenant',
            'perPage' => 200,
        ]);
        return $result['items'] ?? [];
    }

    private function esc(string $value): string
    {
        return str_replace(["'", '"', '\\'], '', $value);
    }
}
