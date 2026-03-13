<?php

declare(strict_types=1);

namespace App;

/**
 * OIDC SSO — Authorization Code Flow with PKCE (RFC 7636).
 *
 * No external dependency — uses cURL and the IdP discovery document
 * (/.well-known/openid-configuration).
 */
class OIDCAuth
{
    private array $config;
    private ?array $discovery = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Build the IdP authorization URL.
     * Stores `state` and `code_verifier` in session for callback verification.
     */
    public function getAuthorizationUrl(): string
    {
        $disc = $this->getDiscovery();

        // PKCE — code_verifier: 43-128 bytes unreserved chars (RFC 7636 §4.1)
        $codeVerifier  = $this->base64urlEncode(random_bytes(32));
        $codeChallenge = $this->base64urlEncode(hash('sha256', $codeVerifier, true));

        $state = bin2hex(random_bytes(16));

        $_SESSION['oidc_state']         = $state;
        $_SESSION['oidc_code_verifier'] = $codeVerifier;

        $params = [
            'response_type'         => 'code',
            'client_id'             => $this->config['client_id'],
            'redirect_uri'          => $this->config['redirect_uri'],
            'scope'                 => $this->config['scopes'] ?? 'openid profile email',
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $disc['authorization_endpoint'] . '?' . http_build_query($params);
    }

    /**
     * Handle the callback from the IdP.
     * Verifies state, exchanges the auth code, fetches userinfo.
     *
     * @return array Normalized user: sub, email, name, username
     * @throws \RuntimeException on any error
     */
    public function handleCallback(string $code, string $state): array
    {
        // CSRF verification via state
        $expectedState = $_SESSION['oidc_state'] ?? '';
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('État OIDC invalide (protection CSRF).');
        }

        $codeVerifier = $_SESSION['oidc_code_verifier'] ?? '';
        unset($_SESSION['oidc_state'], $_SESSION['oidc_code_verifier']);

        $disc   = $this->getDiscovery();
        $tokens = $this->exchangeCode($disc['token_endpoint'], $code, $codeVerifier);

        // Merge ID token claims (fallback) with userinfo (authoritative)
        $idClaims = $this->parseIdTokenClaims($tokens['id_token'] ?? '');
        $userinfo  = [];
        if (!empty($disc['userinfo_endpoint']) && !empty($tokens['access_token'])) {
            try {
                $userinfo = $this->getUserInfo($disc['userinfo_endpoint'], $tokens['access_token']);
            } catch (\Exception $e) {
                error_log('OIDC userinfo fetch failed (using ID token): ' . $e->getMessage());
            }
        }

        $claims = array_merge($idClaims, $userinfo);
        return $this->normalizeUser($claims);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getDiscovery(): array
    {
        if ($this->discovery !== null) {
            return $this->discovery;
        }

        $url  = rtrim($this->config['provider_url'], '/') . '/.well-known/openid-configuration';
        $body = $this->httpGet($url);
        $data = json_decode($body, true);

        if (!isset($data['authorization_endpoint'])) {
            throw new \RuntimeException('Document de découverte OIDC invalide (authorization_endpoint manquant).');
        }

        $this->discovery = $data;
        return $data;
    }

    private function exchangeCode(string $tokenEndpoint, string $code, string $codeVerifier): array
    {
        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->config['redirect_uri'],
            'client_id'     => $this->config['client_id'],
            'code_verifier' => $codeVerifier,
        ];

        if (!empty($this->config['client_secret'])) {
            $params['client_secret'] = $this->config['client_secret'];
        }

        $body = $this->httpPost($tokenEndpoint, $params);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            $desc = $data['error_description'] ?? $data['error'] ?? 'réponse invalide';
            throw new \RuntimeException('Échange de code OIDC échoué : ' . $desc);
        }

        return $data;
    }

    private function getUserInfo(string $userinfoEndpoint, string $accessToken): array
    {
        $body = $this->httpGet($userinfoEndpoint, $accessToken);
        return json_decode($body, true) ?? [];
    }

    private function parseIdTokenClaims(string $idToken): array
    {
        if ($idToken === '') {
            return [];
        }

        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return [];
        }

        // Base64url decode the payload (second segment)
        $padded  = strtr($parts[1], '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $payload = base64_decode($padded, true);
        if ($payload === false) {
            return [];
        }

        return json_decode($payload, true) ?? [];
    }

    private function normalizeUser(array $claims): array
    {
        $usernameClaim = $this->config['username_claim'] ?? 'preferred_username';
        $raw = $claims[$usernameClaim]
            ?? $claims['preferred_username']
            ?? $claims['email']
            ?? $claims['sub']
            ?? '';

        // Strip domain suffix (user@domain → user)
        $username = str_contains((string)$raw, '@')
            ? explode('@', (string)$raw)[0]
            : (string)$raw;

        return [
            'sub'      => (string)($claims['sub'] ?? ''),
            'email'    => strtolower(trim((string)($claims['email'] ?? ''))),
            'name'     => (string)($claims['name'] ?? $claims['cn'] ?? $username),
            'username' => $username,
        ];
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function httpGet(string $url, string $bearerToken = ''): string
    {
        $headers = ['Accept: application/json'];
        if ($bearerToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Requête HTTP OIDC (GET) échouée : ' . $err);
        }
        return (string)$response;
    }

    private function httpPost(string $url, array $params): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Requête HTTP OIDC (POST) échouée : ' . $err);
        }
        return (string)$response;
    }
}
