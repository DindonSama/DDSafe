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
        $result = $this->pb->listRecords('otp_codes', [
            'filter'  => 'deleted=true',
            'sort'    => '-deleted_at',
            'perPage' => 200,
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
        $result = $this->pb->listRecords('otp_codes', [
            'filter'  => $filter,
            'sort'    => 'name',
            'perPage' => 200,
        ]);
        return array_map(fn($r) => $this->withDecryptedSecret($r), $result['items'] ?? []);
    }

    public function getTenantCodes(string $tenantId, string $search = ''): array
    {
        $filter = "tenant='{$this->esc($tenantId)}' && is_personal=false && deleted!=true";
        if ($search !== '') {
            $filter .= " && (name~'{$this->esc($search)}' || issuer~'{$this->esc($search)}')";
        }
        $result = $this->pb->listRecords('otp_codes', [
            'filter'  => $filter,
            'sort'    => 'name',
            'perPage' => 200,
        ]);
        return array_map(fn($r) => $this->withDecryptedSecret($r), $result['items'] ?? []);
    }

    // ── OTP code generation ─────────────────────────────────────

    public function generateCode(array $record): array
    {
        $secret = $record['secret'] ?? $this->decrypt($record['secret_enc'] ?? '');
        if (!$secret) {
            return ['code' => '------', 'remaining' => 0];
        }

        $algo   = strtolower($record['algorithm'] ?? 'sha1');
        $digits = (int)($record['digits'] ?? 6);
        $period = (int)($record['period'] ?? 30);

        $otp = TOTP::createFromSecret($secret);
        $otp->setDigest($algo);
        $otp->setDigits($digits);
        $otp->setPeriod($period);

        $remaining = $period - (time() % $period);

        return [
            'code'      => $otp->now(),
            'remaining' => $remaining,
            'period'    => $period,
        ];
    }

    public function generateCodes(array $ids): array
    {
        $codes = [];
        foreach ($ids as $id) {
            $record = $this->getById($id);
            if ($record) {
                $codes[$id] = $this->generateCode($record);
            }
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
