<?php

/** @var string $path */
/** @var string $method */
/** @var \App\OTPManager $otpManager */
/** @var \App\TenantManager $tenantManager */
/** @var array $currentUser */

header('Content-Type: application/json');

// CSRF check via header for AJAX
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($method === 'POST' && !hash_equals(csrfToken(), $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF invalide']);
    exit;
}

// ── GET /api/otp/codes — bulk fetch current TOTP codes ───────────
if ($path === '/api/otp/codes' && $method === 'GET') {
    $rawIds = $_GET['ids'] ?? '';
    $ids    = array_filter(explode(',', $rawIds), fn($v) => $v !== '');
    $ids    = array_map(fn($v) => preg_replace('/[^a-zA-Z0-9]/', '', $v), $ids);

    $codes = $otpManager->generateCodes($ids);
    echo json_encode(['codes' => $codes]);
    exit;
}

// ── GET /api/otp/overview — read-only OTP list with live codes ───────────
if ($path === '/api/otp/overview' && $method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $personalEnabled = !empty($currentUser['allow_personal_otp']);

    $personal = $personalEnabled
        ? $otpManager->getPersonalCodes((string)$currentUser['id'], $q)
        : [];

    $tenantItems = [];
    $userTenants = $tenantManager->getUserTenants((string)$currentUser['id']);
    foreach ($userTenants as $tenant) {
        $tenantId = (string)($tenant['id'] ?? '');
        if ($tenantId === '') {
            continue;
        }
        $codes = $otpManager->getTenantCodes($tenantId, $q);
        foreach ($codes as $code) {
            $code['_tenant_name'] = (string)($tenant['name'] ?? '');
            $tenantItems[] = $code;
        }
    }

    $all = array_merge($personal, $tenantItems);
    $ids = array_values(array_filter(array_map(
        fn($c) => preg_replace('/[^a-zA-Z0-9]/', '', (string)($c['id'] ?? '')),
        $all
    )));
    $live = $otpManager->generateCodes($ids);

    $items = [];
    foreach ($all as $c) {
        $id = (string)($c['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $info = $live[$id] ?? ['code' => '------', 'remaining' => 0, 'period' => 30];
        $items[] = [
            'id' => $id,
            'name' => (string)($c['name'] ?? ''),
            'issuer' => (string)($c['issuer'] ?? ''),
            'tenant' => (string)($c['_tenant_name'] ?? ''),
            'is_personal' => !empty($c['is_personal']),
            'code' => (string)($info['code'] ?? '------'),
            'remaining' => (int)($info['remaining'] ?? 0),
            'period' => (int)($info['period'] ?? 30),
        ];
    }

    echo json_encode(['items' => $items]);
    exit;
}

// ── POST /api/otp/parse-qr — parse otpauth URI ──────────────────
if ($path === '/api/otp/parse-qr' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $uri   = $input['uri'] ?? '';

    $parsed = $otpManager->parseOtpauthUri($uri);
    if ($parsed) {
        echo json_encode(['success' => true, 'data' => $parsed]);
    } else {
        echo json_encode(['success' => false, 'error' => 'URI invalide']);
    }
    exit;
}

// ── GET /api/search — search OTP codes ───────────────────────────
if ($path === '/api/search' && $method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        echo json_encode(['personal' => [], 'tenant' => []]);
        exit;
    }

    $personal = !empty($currentUser['allow_personal_otp'])
        ? $otpManager->getPersonalCodes($currentUser['id'], $q)
        : [];
    $tenantId = $_SESSION['current_tenant'] ?? null;
    $tenant   = $tenantId ? $otpManager->getTenantCodes($tenantId, $q) : [];

    // Strip secrets from response
    $strip = function (array $codes): array {
        return array_map(function ($c) {
            unset($c['secret'], $c['secret_enc']);
            return $c;
        }, $codes);
    };

    echo json_encode([
        'personal' => $strip($personal),
        'tenant'   => $strip($tenant),
    ]);
    exit;
}

// ── GET /api/users/search — search users (for member add) ───────
if ($path === '/api/users/search' && $method === 'GET') {
    $q     = trim($_GET['q'] ?? '');
    $users = $q ? $tenantManager->searchUsers($q) : [];
    $safe  = array_map(fn($u) => [
        'id'    => $u['id'],
        'email' => $u['email'] ?? '',
        'name'  => $u['name'] ?? '',
    ], $users);
    echo json_encode(['users' => $safe]);
    exit;
}

// ── POST /api/otp/parse-bulk — parse multiple otpauth URIs ──────
if ($path === '/api/otp/parse-bulk' && $method === 'POST') {
    $input   = json_decode(file_get_contents('php://input'), true);
    $uris    = array_values(array_filter(
        (array)($input['uris'] ?? []),
        fn($u) => is_string($u) && str_starts_with($u, 'otpauth://')
    ));
    $results = [];
    foreach ($uris as $uri) {
        $parsed = $otpManager->parseOtpauthUri($uri);
        if ($parsed) {
            $results[] = ['data' => $parsed];
        }
    }
    echo json_encode(['results' => $results]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Route API introuvable']);
