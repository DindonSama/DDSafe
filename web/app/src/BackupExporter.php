<?php

declare(strict_types=1);

namespace App;

class BackupExporter
{
    private PocketBaseClient $pb;

    public function __construct(PocketBaseClient $pb)
    {
        $this->pb = $pb;
    }

    public function buildPayload(array $config, bool $includeSecrets = false): array
    {
        $users = $this->safeListAllRecords('users', ['sort' => 'email']);
        $tenants = $this->safeListAllRecords('tenants', ['sort' => 'name']);
        $memberships = $this->safeListAllRecords('tenant_members', ['sort' => 'created']);
        $groups = $this->safeListAllRecords('otp_groups', ['sort' => 'name']);
        $codes = $this->safeListAllRecords('otp_codes', ['sort' => 'name']);

        if (!$includeSecrets) {
            foreach ($codes as &$code) {
                unset($code['secret_enc']);
            }
            unset($code);
        }

        return [
            'version' => 1,
            'generated_at' => date('c'),
            'include_secrets' => $includeSecrets,
            'config' => $this->sanitizeConfig($config, $includeSecrets),
            'metadata' => [
                'stats' => [
                    'users' => count($users),
                    'tenants' => count($tenants),
                    'tenant_memberships' => count($memberships),
                    'otp_groups' => count($groups),
                    'otp_codes' => count($codes),
                ],
                'users' => $users,
                'tenants' => $tenants,
                'tenant_members' => $memberships,
                'otp_groups' => $groups,
                'otp_codes' => $codes,
            ],
        ];
    }

    public function encryptPayload(array $payload, string $passphrase): string
    {
        $plain = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($plain === false) {
            throw new \RuntimeException('Impossible d\'encoder le backup.');
        }

        $salt = random_bytes(16);
        $iv = random_bytes(16);
        $iterations = 120000;
        $key = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 32, true);

        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Impossible de chiffrer le backup.');
        }

        $envelope = [
            'format' => 'DDSAFE_BACKUP_V1',
            'cipher' => 'AES-256-CBC',
            'kdf' => 'PBKDF2-SHA256',
            'iterations' => $iterations,
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'data' => base64_encode($cipher),
            'generated_at' => date('c'),
        ];

        return json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }

    public function decodeBackup(string $raw, string $passphrase = ''): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Fichier backup invalide (JSON illisible).');
        }

        if (($decoded['format'] ?? '') !== 'DDSAFE_BACKUP_V1') {
            return $decoded;
        }

        if ($passphrase === '') {
            throw new \RuntimeException('Phrase de passe requise pour ce backup chiffré.');
        }

        $iterations = (int)($decoded['iterations'] ?? 0);
        $salt = base64_decode((string)($decoded['salt'] ?? ''), true);
        $iv = base64_decode((string)($decoded['iv'] ?? ''), true);
        $data = base64_decode((string)($decoded['data'] ?? ''), true);

        if ($iterations <= 0 || $salt === false || $iv === false || $data === false) {
            throw new \RuntimeException('Enveloppe chiffrée invalide.');
        }

        $key = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 32, true);
        $plain = openssl_decrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Déchiffrement impossible (phrase de passe incorrecte ?).');
        }

        $payload = json_decode($plain, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Payload déchiffré invalide.');
        }

        return $payload;
    }

    public function verifyPayload(array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        $users = is_array($metadata['users'] ?? null) ? $metadata['users'] : [];
        $tenants = is_array($metadata['tenants'] ?? null) ? $metadata['tenants'] : [];
        $groups = is_array($metadata['otp_groups'] ?? null) ? $metadata['otp_groups'] : [];
        $codes = is_array($metadata['otp_codes'] ?? null) ? $metadata['otp_codes'] : [];

        $withSecret = 0;
        $withoutSecret = 0;
        foreach ($codes as $code) {
            if (!empty($code['secret_enc'])) {
                $withSecret++;
            } else {
                $withoutSecret++;
            }
        }

        return [
            'version' => (int)($payload['version'] ?? 0),
            'generated_at' => (string)($payload['generated_at'] ?? ''),
            'include_secrets' => !empty($payload['include_secrets']),
            'stats' => [
                'users' => count($users),
                'tenants' => count($tenants),
                'otp_groups' => count($groups),
                'otp_codes' => count($codes),
                'otp_codes_with_secret' => $withSecret,
                'otp_codes_without_secret' => $withoutSecret,
            ],
        ];
    }

    public function importPayload(array $payload, bool $overwrite = false): array
    {
        $metadata = $payload['metadata'] ?? [];
        $users = is_array($metadata['users'] ?? null) ? $metadata['users'] : [];
        $tenants = is_array($metadata['tenants'] ?? null) ? $metadata['tenants'] : [];
        $groups = is_array($metadata['otp_groups'] ?? null) ? $metadata['otp_groups'] : [];
        $codes = is_array($metadata['otp_codes'] ?? null) ? $metadata['otp_codes'] : [];

        $existingUsers = $this->safeListAllRecords('users', ['sort' => 'email']);
        $userIdByEmail = [];
        foreach ($existingUsers as $user) {
            $email = strtolower(trim((string)($user['email'] ?? '')));
            if ($email !== '') {
                $userIdByEmail[$email] = (string)($user['id'] ?? '');
            }
        }

        $tenantMap = [];
        $createdTenants = 0;
        foreach ($tenants as $tenant) {
            if (!empty($tenant['deleted'])) {
                continue;
            }
            $oldId = (string)($tenant['id'] ?? '');
            $name = trim((string)($tenant['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $existing = $this->findOne('tenants', "name='" . $this->esc($name) . "' && deleted!=true");
            if ($existing) {
                $tenantMap[$oldId] = (string)($existing['id'] ?? '');
                continue;
            }

            $created = $this->pb->createRecord('tenants', [
                'name' => $name,
                'description' => (string)($tenant['description'] ?? ''),
                'created_by' => '',
            ]);
            $tenantMap[$oldId] = (string)($created['id'] ?? '');
            $createdTenants++;
        }

        $groupMap = [];
        $createdGroups = 0;
        foreach ($groups as $group) {
            $oldId = (string)($group['id'] ?? '');
            $name = trim((string)($group['name'] ?? ''));
            $oldTenantId = (string)($group['tenant'] ?? '');
            $newTenantId = (string)($tenantMap[$oldTenantId] ?? '');
            if ($name === '' || $newTenantId === '') {
                continue;
            }

            $existing = $this->findOne('otp_groups', "tenant='" . $this->esc($newTenantId) . "' && name='" . $this->esc($name) . "'");
            if ($existing) {
                $groupMap[$oldId] = (string)($existing['id'] ?? '');
                continue;
            }

            $created = $this->pb->createRecord('otp_groups', [
                'name' => $name,
                'tenant' => $newTenantId,
                'created_by' => '',
            ]);
            $groupMap[$oldId] = (string)($created['id'] ?? '');
            $createdGroups++;
        }

        $createdCodes = 0;
        $updatedCodes = 0;
        $skippedCodes = 0;
        foreach ($codes as $code) {
            if (!empty($code['deleted'])) {
                continue;
            }

            $name = trim((string)($code['name'] ?? ''));
            $secretEnc = (string)($code['secret_enc'] ?? '');
            if ($name === '' || $secretEnc === '') {
                $skippedCodes++;
                continue;
            }

            $isPersonal = !empty($code['is_personal']);
            $oldTenantId = (string)($code['tenant'] ?? '');
            $tenant = $isPersonal ? '' : (string)($tenantMap[$oldTenantId] ?? '');
            if (!$isPersonal && $tenant === '') {
                $skippedCodes++;
                continue;
            }

            $ownerId = '';
            $oldOwnerId = (string)($code['owner'] ?? '');
            if ($oldOwnerId !== '') {
                foreach ($users as $u) {
                    if ((string)($u['id'] ?? '') === $oldOwnerId) {
                        $email = strtolower(trim((string)($u['email'] ?? '')));
                        if ($email !== '' && isset($userIdByEmail[$email])) {
                            $ownerId = (string)$userIdByEmail[$email];
                        }
                        break;
                    }
                }
            }

            $oldGroupId = (string)($code['group'] ?? '');
            $group = (string)($groupMap[$oldGroupId] ?? '');

            $payloadCode = [
                'name' => $name,
                'issuer' => (string)($code['issuer'] ?? ''),
                'secret_enc' => $secretEnc,
                'algorithm' => (string)($code['algorithm'] ?? 'SHA1'),
                'digits' => (int)($code['digits'] ?? 6),
                'period' => (int)($code['period'] ?? 30),
                'type' => (string)($code['type'] ?? 'totp'),
                'counter' => (int)($code['counter'] ?? 0),
                'owner' => $ownerId,
                'tenant' => $tenant,
                'group' => $group,
                'is_personal' => $isPersonal,
                'deleted' => false,
                'deleted_by' => '',
                'deleted_at' => '',
            ];

            $filter = "name='" . $this->esc($name) . "'"
                . " && issuer='" . $this->esc((string)($code['issuer'] ?? '')) . "'"
                . " && is_personal=" . ($isPersonal ? 'true' : 'false')
                . ($tenant !== '' ? " && tenant='" . $this->esc($tenant) . "'" : " && (tenant='' || tenant=null)");

            $existingCode = $this->findOne('otp_codes', $filter);
            if ($existingCode && $overwrite) {
                $this->pb->updateRecord('otp_codes', (string)$existingCode['id'], $payloadCode);
                $updatedCodes++;
            } elseif (!$existingCode) {
                $this->pb->createRecord('otp_codes', $payloadCode);
                $createdCodes++;
            }
        }

        return [
            'created_tenants' => $createdTenants,
            'created_groups' => $createdGroups,
            'created_codes' => $createdCodes,
            'updated_codes' => $updatedCodes,
            'skipped_codes' => $skippedCodes,
        ];
    }

    private function listAllRecords(string $collection, array $params = []): array
    {
        $items = [];
        $page = 1;

        do {
            $result = $this->pb->listRecords($collection, array_merge($params, [
                'page' => $page,
                'perPage' => 200,
            ]));

            $chunk = $result['items'] ?? [];
            foreach ($chunk as $row) {
                $items[] = $row;
            }

            $totalPages = (int)($result['totalPages'] ?? 1);
            $page++;
        } while (!empty($chunk) && $page <= $totalPages);

        return $items;
    }

    private function safeListAllRecords(string $collection, array $params = []): array
    {
        try {
            return $this->listAllRecords($collection, $params);
        } catch (\Exception) {
            return [];
        }
    }

    private function findOne(string $collection, string $filter): ?array
    {
        try {
            $result = $this->pb->listRecords($collection, [
                'filter' => $filter,
                'perPage' => 1,
                'page' => 1,
            ]);
            return $result['items'][0] ?? null;
        } catch (\Exception) {
            return null;
        }
    }

    private function esc(string $value): string
    {
        return str_replace(["'", '"', '\\'], '', $value);
    }

    private function sanitizeConfig(array $config, bool $includeSecrets): array
    {
        if ($includeSecrets) {
            return $config;
        }

        $safe = $config;
        $safe['app_secret'] = '[REDACTED]';

        if (isset($safe['default_admin']['password'])) {
            $safe['default_admin']['password'] = '[REDACTED]';
        }
        if (isset($safe['pocketbase']['admin_password'])) {
            $safe['pocketbase']['admin_password'] = '[REDACTED]';
        }
        if (isset($safe['oidc']['client_secret'])) {
            $safe['oidc']['client_secret'] = '[REDACTED]';
        }
        if (isset($safe['ldap']['bind_password'])) {
            $safe['ldap']['bind_password'] = '[REDACTED]';
        }

        return $safe;
    }
}
