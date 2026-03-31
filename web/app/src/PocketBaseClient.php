<?php

declare(strict_types=1);

namespace App;

class PocketBaseClient
{
    private string $baseUrl;
    private ?string $token = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    // ── Admin auth ──────────────────────────────────────────────
    public function authAdmin(string $email, string $password): array
    {
        return $this->request('POST', '/api/collections/_superusers/auth-with-password', [
            'identity' => $email,
            'password' => $password,
        ], false);
    }

    public function createAdmin(string $email, string $password): array
    {
        return $this->request('POST', '/api/collections/_superusers/records', [
            'email'           => $email,
            'password'        => $password,
            'passwordConfirm' => $password,
        ], false);
    }

    // ── User auth ───────────────────────────────────────────────
    public function authUser(string $identity, string $password): array
    {
        return $this->request('POST', '/api/collections/users/auth-with-password', [
            'identity' => $identity,
            'password' => $password,
        ], false);
    }

    // ── Collection management ───────────────────────────────────
    public function listCollections(): array
    {
        $result = $this->request('GET', '/api/collections?perPage=200');
        return $result['items'] ?? [];
    }

    public function getCollection(string $nameOrId): ?array
    {
        try {
            return $this->request('GET', "/api/collections/{$nameOrId}");
        } catch (\Exception) {
            return null;
        }
    }

    public function createCollection(array $data): array
    {
        return $this->request('POST', '/api/collections', $data);
    }

    public function updateCollection(string $id, array $data): array
    {
        return $this->request('PATCH', "/api/collections/{$id}", $data);
    }

    // ── CRUD records ────────────────────────────────────────────
    public function listRecords(string $collection, array $params = []): array
    {
        $query = http_build_query($params);
        $url   = "/api/collections/{$collection}/records" . ($query ? "?{$query}" : '');
        return $this->request('GET', $url);
    }

    public function getRecord(string $collection, string $id, array $params = []): ?array
    {
        try {
            $query = http_build_query($params);
            $url   = "/api/collections/{$collection}/records/{$id}" . ($query ? "?{$query}" : '');
            return $this->request('GET', $url);
        } catch (\Exception) {
            return null;
        }
    }

    public function createRecord(string $collection, array $data): array
    {
        return $this->request('POST', "/api/collections/{$collection}/records", $data);
    }

    public function updateRecord(string $collection, string $id, array $data): array
    {
        return $this->request('PATCH', "/api/collections/{$collection}/records/{$id}", $data);
    }

    public function deleteRecord(string $collection, string $id): bool
    {
        try {
            $this->request('DELETE', "/api/collections/{$collection}/records/{$id}");
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    // ── PocketBase backups API ─────────────────────────────────
    public function listBackups(): array
    {
        $result = $this->request('GET', '/api/backups');
        return $result['items'] ?? $result;
    }

    public function createBackup(?string $name = null): array
    {
        $payload = [];
        if ($name !== null && trim($name) !== '') {
            $payload['name'] = trim($name);
        }
        return $this->request('POST', '/api/backups', $payload);
    }

    public function deleteBackup(string $name): void
    {
        $encodedName = rawurlencode($name);
        $this->request('DELETE', "/api/backups/{$encodedName}");
    }

    public function restoreBackup(string $name): void
    {
        $encodedName = rawurlencode($name);
        $this->request('POST', "/api/backups/{$encodedName}/restore", []);
    }

    public function downloadBackup(string $name): string
    {
        $encodedName = rawurlencode($name);
        return $this->requestBody('GET', "/api/backups/{$encodedName}");
    }

    // ── HTTP layer ──────────────────────────────────────────────
    private function request(string $method, string $url, ?array $data = null, bool $useToken = true): array
    {
        $response = $this->requestBody($method, $url, $data, $useToken);
        return json_decode($response, true) ?: [];
    }

    private function requestBody(string $method, string $url, ?array $data = null, bool $useToken = true): string
    {
        $ch      = curl_init();
        $fullUrl = $this->baseUrl . $url;

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($useToken && $this->token) {
            $headers[] = 'Authorization: ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }
        if ($httpCode >= 400) {
            $body = json_decode($response, true);
            $msg  = $body['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("PocketBase: {$msg}", $httpCode);
        }

        return (string)$response;
    }
}
