<?php

declare(strict_types=1);

namespace App;

use OTPHP\TOTP;
use OTPHP\HOTP;
use OTPHP\OTPInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class OTPManager
{
    private PocketBaseClient $pb;
    private string $encKey;

    public function __construct(PocketBaseClient $pb, string $appSecret)
    {
        $this->pb     = $pb;
        $this->encKey = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    // ── CRUD ────────────────────────────────────────────────────

    public function create(array $data): array
    {
        $data['secret_enc'] = $this->encrypt($data['secret'] ?? '');
        unset($data['secret']);
        return $this->pb->createRecord('otp_codes', $data);
    }

    public function update(string $id, array $data, string $userId): array
    {
        if (isset($data['secret'])) {
            $data['secret_enc'] = $this->encrypt($data['secret']);
            unset($data['secret']);
        }
        return $this->pb->updateRecord('otp_codes', $id, $data);
    }

    public function delete(string $id, string $userId): bool
    {
        // Soft-delete: mark as deleted with who and when
        $this->pb->updateRecord('otp_codes', $id, [
            'deleted'    => true,
            'deleted_by' => $userId,
            'deleted_at' => date('c'),
        ]);
        return true;
    }

    public function restore(string $id): bool
    {
        $this->pb->updateRecord('otp_codes', $id, [
            'deleted'    => false,
            'deleted_by' => '',
            'deleted_at' => '',
        ]);
        return true;
    }

    public function permanentDelete(string $id): bool
    {
        return $this->pb->deleteRecord('otp_codes', $id);
    }

    public function getTrashedCodes(): array
    {
        $result = $this->pb->listAllRecords('otp_codes', [
            'filter'  => 'deleted=true',
            'sort'    => '-deleted_at',
            'expand'  => 'deleted_by,owner,tenant',
        ]);
        return $result['items'] ?? [];
    }

    public function getById(string $id): ?array
    {
        $record = $this->pb->getRecord('otp_codes', $id);
        if ($record) {
            $record['secret'] = $this->decrypt($record['secret_enc'] ?? '');
        }
        return $record;
    }

    public function getPersonalCodes(string $userId, string $search = ''): array
    {
        $filter = "owner='{$this->esc($userId)}' && is_personal=true && deleted!=true";
        if ($search !== '') {
            $filter .= " && (name~'{$this->esc($search)}' || issuer~'{$this->esc($search)}')";
        }
        $result = $this->pb->listAllRecords('otp_codes', [
            'filter' => $filter,
            'sort'   => 'name',
        ]);
        return array_map(fn($r) => $this->withDecryptedSecret($r), $result['items'] ?? []);
    }

    public function getTenantCodes(string $tenantId, string $search = ''): array
    {
        return $this->getTenantCodesByGroup($tenantId, null, $search);
    }

    public function getTenantCodesByGroup(string $tenantId, ?string $groupId = null, string $search = ''): array
    {
        $records = $this->listTenantCodeRecords($tenantId, $groupId, $search, 'group');
        return array_map(fn($r) => $this->withDecryptedSecret($r), $records);
    }

    public function getTenantGroups(string $tenantId): array
    {
        $result = $this->pb->listAllRecords('otp_groups', [
            'filter' => "tenant='{$this->esc($tenantId)}'",
            'sort'   => 'name',
        ]);
        return $result['items'] ?? [];
    }

    public function getTenantFolderSummary(string $tenantId): array
    {
        $groups = $this->getTenantGroups($tenantId);
        $records = $this->listTenantCodeRecords($tenantId, null, '');

        $counts = [];
        $rootCount = 0;
        foreach ($records as $record) {
            $groupId = (string)($record['group'] ?? '');
            if ($groupId === '') {
                $rootCount++;
                continue;
            }
            $counts[$groupId] = ($counts[$groupId] ?? 0) + 1;
        }

        foreach ($groups as &$group) {
            $group['code_count'] = $counts[(string)($group['id'] ?? '')] ?? 0;
        }
        unset($group);

        return [
            'folders' => $groups,
            'root_count' => $rootCount,
        ];
    }

    public function getGroupById(string $groupId): ?array
    {
        return $this->pb->getRecord('otp_groups', $groupId);
    }

    public function getFavoriteCodeIds(string $userId): array
    {
        $result = $this->pb->listRecords('otp_favorites', [
            'filter'  => "user='{$this->esc($userId)}'",
            'perPage' => 500,
        ]);

        $ids = [];
        foreach (($result['items'] ?? []) as $item) {
            $otpId = (string)($item['otp'] ?? '');
            if ($otpId !== '') {
                $ids[$otpId] = true;
            }
        }

        return array_keys($ids);
    }

    public function isFavorite(string $userId, string $otpId): bool
    {
        $result = $this->pb->listRecords('otp_favorites', [
            'filter'  => "user='{$this->esc($userId)}' && otp='{$this->esc($otpId)}'",
            'perPage' => 1,
        ]);

        return !empty($result['items']);
    }

    public function setFavorite(string $userId, string $otpId): void
    {
        if ($this->isFavorite($userId, $otpId)) {
            return;
        }

        $this->pb->createRecord('otp_favorites', [
            'user' => $userId,
            'otp'  => $otpId,
        ]);
    }

    public function removeFavorite(string $userId, string $otpId): void
    {
        $result = $this->pb->listRecords('otp_favorites', [
            'filter'  => "user='{$this->esc($userId)}' && otp='{$this->esc($otpId)}'",
            'perPage' => 200,
        ]);

        foreach (($result['items'] ?? []) as $item) {
            $id = (string)($item['id'] ?? '');
            if ($id !== '') {
                $this->pb->deleteRecord('otp_favorites', $id);
            }
        }
    }

    public function toggleFavorite(string $userId, string $otpId): bool
    {
        if ($this->isFavorite($userId, $otpId)) {
            $this->removeFavorite($userId, $otpId);
            return false;
        }

        $this->setFavorite($userId, $otpId);
        return true;
    }

    public function createGroup(string $tenantId, string $name, string $createdBy): array
    {
        return $this->pb->createRecord('otp_groups', [
            'name'       => $name,
            'tenant'     => $tenantId,
            'created_by' => $createdBy,
        ]);
    }

    public function renameGroup(string $groupId, string $tenantId, string $newName): void
    {
        $group = $this->pb->getRecord('otp_groups', $groupId);
        if (!$group || (string)($group['tenant'] ?? '') !== $tenantId) {
            throw new \RuntimeException('Dossier introuvable ou accès refusé.');
        }
        $this->pb->updateRecord('otp_groups', $groupId, ['name' => $newName]);
    }

    public function deleteGroup(string $groupId, string $tenantId): int
    {
        $movedCount = 0;
        $page = 1;

        do {
            $result = $this->pb->listRecords('otp_codes', [
                'filter'  => "tenant='{$this->esc($tenantId)}' && group='{$this->esc($groupId)}'",
                'sort'    => 'name',
                'perPage' => 200,
                'page'    => $page,
            ]);

            $items = $result['items'] ?? [];
            foreach ($items as $record) {
                $id = (string)($record['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $this->pb->updateRecord('otp_codes', $id, ['group' => '']);
                $movedCount++;
            }

            $totalPages = (int)($result['totalPages'] ?? $page);
            $page++;
        } while (!empty($items) && $page <= $totalPages);

        if (!$this->pb->deleteRecord('otp_groups', $groupId)) {
            throw new \RuntimeException('Suppression du dossier impossible.');
        }

        return $movedCount;
    }

    private function listTenantCodeRecords(string $tenantId, ?string $groupId = null, string $search = '', string $expand = ''): array
    {
        $filter = "tenant='{$this->esc($tenantId)}' && is_personal=false && deleted!=true";
        if ($groupId !== null) {
            if ($groupId === '') {
                $filter .= ' && (group="" || group=null)';
            } else {
                $filter .= " && group='{$this->esc($groupId)}'";
            }
        }
        if ($search !== '') {
            $filter .= " && (name~'{$this->esc($search)}' || issuer~'{$this->esc($search)}')";
        }

        $params = [
            'filter' => $filter,
            'sort'   => 'name',
        ];
        if ($expand !== '') {
            $params['expand'] = $expand;
        }

        $result = $this->pb->listAllRecords('otp_codes', $params);
        return $result['items'] ?? [];
    }

    // ── OTP code generation ─────────────────────────────────────

    public function generateCode(array $record): array
    {
        $secret = $record['secret'] ?? $this->decrypt($record['secret_enc'] ?? '');
        if (!$secret) {
            return ['code' => '------', 'next_code' => '------', 'remaining' => 0];
        }

        $algo   = strtolower($record['algorithm'] ?? 'sha1');
        $digits = (int)($record['digits'] ?? 6);
        $period = (int)($record['period'] ?? 30);

        $otp = TOTP::createFromSecret($secret);
        $otp->setDigest($algo);
        $otp->setDigits($digits);
        $otp->setPeriod($period);

        $now = time();
        $remaining = $period - ($now % $period);
        $nextAt = $now + $remaining;

        return [
            'code'      => $otp->at($now),
            'next_code' => $otp->at($nextAt),
            'remaining' => $remaining,
            'period'    => $period,
        ];
    }

    public function generateCodes(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Build a single filter to fetch all needed records in one PocketBase request
        $escaped = array_map(fn($id) => "id='{$this->esc($id)}'", $ids);
        $filter  = implode('||', $escaped);

        $result  = $this->pb->listRecords('otp_codes', [
            'filter'  => $filter,
            'perPage' => count($ids),
        ]);

        $codes = [];
        foreach (($result['items'] ?? []) as $record) {
            $id = (string)($record['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $record['secret'] = $this->decrypt($record['secret_enc'] ?? '');
            $codes[$id] = $this->generateCode($record);
        }
        return $codes;
    }

    // ── otpauth:// URI parsing ──────────────────────────────────

    public function parseOtpauthUri(string $uri): ?array
    {
        if (!str_starts_with($uri, 'otpauth://')) {
            return null;
        }

        $parsed = parse_url($uri);
        if (!$parsed) {
            return null;
        }

        $type  = $parsed['host'] ?? 'totp'; // totp or hotp
        $label = ltrim(urldecode($parsed['path'] ?? ''), '/');

        parse_str($parsed['query'] ?? '', $params);

        $issuer = $params['issuer'] ?? '';
        $name   = $label;

        // If label contains "issuer:account", split it
        if (str_contains($label, ':')) {
            [$issuerFromLabel, $name] = explode(':', $label, 2);
            if (empty($issuer)) {
                $issuer = $issuerFromLabel;
            }
        }

        return [
            'name'      => trim($name),
            'issuer'    => trim($issuer),
            'secret'    => $params['secret'] ?? '',
            'algorithm' => strtoupper($params['algorithm'] ?? 'SHA1'),
            'digits'    => (int)($params['digits'] ?? 6),
            'period'    => (int)($params['period'] ?? 30),
            'type'      => strtolower($type),
            'counter'   => (int)($params['counter'] ?? 0),
        ];
    }

    // ── otpauth:// URI building ─────────────────────────────────

    public function buildOtpauthUri(array $record): string
    {
        $secret = $record['secret'] ?? $this->decrypt($record['secret_enc'] ?? '');
        $type   = $record['type'] ?? 'totp';
        $label  = $record['name'] ?? 'Code';
        $issuer = $record['issuer'] ?? '';

        if ($issuer) {
            $labelFull = rawurlencode($issuer) . ':' . rawurlencode($label);
        } else {
            $labelFull = rawurlencode($label);
        }

        $params = [
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => $record['algorithm'] ?? 'SHA1',
            'digits'    => $record['digits'] ?? 6,
            'period'    => $record['period'] ?? 30,
        ];

        return "otpauth://{$type}/{$labelFull}?" . http_build_query($params);
    }

    // ── QR code generation ──────────────────────────────────────

    public function generateQrSvg(string $data): string
    {
        $options = new QROptions([
            'outputType'    => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'      => QRCode::ECC_L,
            'addQuietzone'  => true,
            'svgUseCssClass' => false,
            'outputBase64'  => false,
        ]);

        return (new QRCode($options))->render($data);
    }

    // ── Encryption ──────────────────────────────────────────────

    private function encrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($data, $nonce, $this->encKey);
        return base64_encode($nonce . $encrypted);
    }

    private function decrypt(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }
        $decoded    = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            return '';
        }
        $nonce      = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain      = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encKey);
        return $plain === false ? '' : $plain;
    }

    private function withDecryptedSecret(array $record): array
    {
        $record['secret'] = $this->decrypt($record['secret_enc'] ?? '');
        return $record;
    }

    private function esc(string $value): string
    {
        return str_replace(["'", '"', '\\'], '', $value);
    }
}
