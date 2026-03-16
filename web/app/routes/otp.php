<?php

/** @var string $path */
/** @var string $method */
/** @var \App\OTPManager $otpManager */
/** @var \App\TenantManager $tenantManager */
/** @var \App\Auth $auth */
/** @var array $currentUser */

// ── Add OTP code ─────────────────────────────────────────────────
if ($path === '/otp/add' && $method === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $secret     = trim($_POST['secret'] ?? '');
    $issuer     = trim($_POST['issuer'] ?? '');
    $algorithm  = $_POST['algorithm'] ?? 'SHA1';
    $digits     = (int)($_POST['digits'] ?? 6);
    $period     = (int)($_POST['period'] ?? 30);
    $personalEnabled = !empty($currentUser['allow_personal_otp']);
    $requestedPersonal = !empty($_POST['is_personal']);
    $isPersonal = $personalEnabled && $requestedPersonal;
    $tenantId   = $_POST['tenant'] ?? '';

    $userTenantsForCreate = $tenantManager->getUserTenants($currentUser['id']);
    $canManageAnyTenantOtp = false;
    foreach ($userTenantsForCreate as $tenant) {
        if ($auth->canDoInTenant((string)($tenant['id'] ?? ''), 'manage_otp')) {
            $canManageAnyTenantOtp = true;
            break;
        }
    }

    if ($requestedPersonal && !$personalEnabled) {
        flash('danger', 'Les OTP personnels ne sont pas autorisés pour votre compte.');
        header('Location: /otp');
        exit;
    }

    if (empty($currentUser['is_app_admin']) && !$canManageAnyTenantOtp && !$personalEnabled) {
        flash('danger', 'Votre role ne permet pas de creer de nouveaux codes OTP.');
        header('Location: /otp');
        exit;
    }

    if ($name === '' || $secret === '') {
        flash('danger', 'Le nom et le secret sont obligatoires.');
        header('Location: /otp');
        exit;
    }

    if (!$isPersonal && $tenantId === '') {
        flash('danger', 'Veuillez sélectionner un tenant pour un code non personnel.');
        header('Location: /otp');
        exit;
    }

    if (!$isPersonal && !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', 'Vous n\'avez pas la permission de creer des codes OTP dans ce tenant.');
        header('Location: /otp');
        exit;
    }

    $otpManager->create([
        'name'        => $name,
        'issuer'      => $issuer,
        'secret'      => $secret,
        'algorithm'   => $algorithm,
        'digits'      => $digits,
        'period'      => $period,
        'type'        => 'totp',
        'is_personal' => $isPersonal,
        'owner'       => $currentUser['id'],
        'tenant'      => $isPersonal ? '' : $tenantId,
    ]);
    flash('success', 'Code OTP ajouté.');
    header('Location: /otp');
    exit;
}

// ── Import page ──────────────────────────────────────────────────
if ($path === '/otp/import' && $method === 'GET') {
    $pageTitle   = 'Importer un code OTP';
    $userTenants = $tenantManager->getUserTenants($currentUser['id']);
    $personalEnabled = !empty($currentUser['allow_personal_otp']);
    $otpWritableTenants = [];
    foreach ($userTenants as $tenant) {
        if ($auth->canDoInTenant((string)$tenant['id'], 'manage_otp')) {
            $otpWritableTenants[] = $tenant;
        }
    }

    $canCreateOtp = !empty($currentUser['is_app_admin']) || !empty($otpWritableTenants) || $personalEnabled;
    if (!$canCreateOtp) {
        flash('danger', 'Votre role ne permet pas de creer/importer des codes OTP.');
        header('Location: /otp');
        exit;
    }
    $prefillUri = trim($_GET['uri'] ?? '');
    // Validate that the prefilled URI is a proper otpauth URI before passing it to the template
    if ($prefillUri !== '' && !str_starts_with($prefillUri, 'otpauth://')) {
        $prefillUri = '';
    }
    require __DIR__ . '/../templates/import.php';
    return;
}

// ── Import process ───────────────────────────────────────────────
if ($path === '/otp/import' && $method === 'POST') {
    $rawInput        = trim($_POST['otp_uri'] ?? '');
    $personalEnabled = !empty($currentUser['allow_personal_otp']);
    $requestedPersonal = !empty($_POST['is_personal']);
    $isPersonal      = $personalEnabled && $requestedPersonal;
    $tenantId        = $_POST['tenant'] ?? '';

    $userTenantsForCreate  = $tenantManager->getUserTenants($currentUser['id']);
    $canManageAnyTenantOtp = false;
    foreach ($userTenantsForCreate as $tenant) {
        if ($auth->canDoInTenant((string)($tenant['id'] ?? ''), 'manage_otp')) {
            $canManageAnyTenantOtp = true;
            break;
        }
    }

    if ($requestedPersonal && !$personalEnabled) {
        flash('danger', 'Les OTP personnels ne sont pas autorisés pour votre compte.');
        header('Location: /otp/import');
        exit;
    }

    if (empty($currentUser['is_app_admin']) && !$canManageAnyTenantOtp && !$personalEnabled) {
        flash('danger', 'Votre role ne permet pas de creer/importer des codes OTP.');
        header('Location: /otp');
        exit;
    }

    // Extract all valid otpauth:// URIs, one per line
    $uris = array_values(array_filter(
        array_map('trim', explode("\n", $rawInput)),
        fn($l) => str_starts_with($l, 'otpauth://')
    ));

    if (empty($uris)) {
        flash('danger', 'Aucune URI otpauth:// valide trouvée.');
        header('Location: /otp/import');
        exit;
    }

    if (!$isPersonal && $tenantId === '') {
        flash('danger', 'Veuillez sélectionner un tenant pour un code non personnel.');
        header('Location: /otp/import');
        exit;
    }

    if (!$isPersonal && !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', "Vous n'avez pas la permission d'importer des codes OTP dans ce tenant.");
        header('Location: /otp/import');
        exit;
    }

    $imported = 0;
    $skipped  = 0;
    foreach ($uris as $uri) {
        $parsed = $otpManager->parseOtpauthUri($uri);
        if (!$parsed) { $skipped++; continue; }
        $parsed['is_personal'] = $isPersonal;
        $parsed['owner']       = $currentUser['id'];
        $parsed['tenant']      = $isPersonal ? '' : $tenantId;
        $otpManager->create($parsed);
        $imported++;
    }

    if ($imported > 0) {
        $s = $imported > 1 ? 's' : '';
        flash('success', "{$imported} code{$s} OTP importé{$s} avec succès.");
    }
    if ($skipped > 0) {
        $e = $skipped > 1 ? 's' : '';
        flash('warning', "{$skipped} URI{$e} invalide{$e} ignorée{$e}.");
    }
    header('Location: /otp');
    exit;
}

// ── Export page ──────────────────────────────────────────────────
if ($path === '/otp/export' && $method === 'GET') {
    $pageTitle = 'Exporter des codes OTP';
    $rawIds    = $_GET['ids'] ?? '';
    $ids       = array_filter(explode(',', $rawIds), fn($v) => $v !== '');
    $codes     = [];
    $qrCodes   = [];
    foreach ($ids as $id) {
        $code = $otpManager->getById(preg_replace('/[^a-zA-Z0-9]/', '', $id));
        if ($code) {
            $codes[]    = $code;
            $uri        = $otpManager->buildOtpauthUri($code);
            $qrCodes[$code['id']] = $otpManager->generateQrSvg($uri);
        }
    }
    require __DIR__ . '/../templates/export.php';
    return;
}

// ── Export form submission ────────────────────────────────────────
if ($path === '/otp/export' && $method === 'POST') {
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        flash('warning', 'Veuillez sélectionner au moins un code.');
        header('Location: /otp');
        exit;
    }
    $clean = array_map(fn($v) => preg_replace('/[^a-zA-Z0-9]/', '', $v), $ids);
    header('Location: /otp/export?ids=' . implode(',', $clean));
    exit;
}

// ── Delete (soft) ────────────────────────────────────────────────
if ($path === '/otp/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $record = $otpManager->getById($id);
        if (!$record) {
            flash('danger', 'Code introuvable.');
            header('Location: /otp');
            exit;
        }

        $isAppPrivileged = !empty($currentUser['is_app_admin']);
        $isOwner = (string)($record['owner'] ?? '') === (string)$currentUser['id'];
        $tenantId = (string)($record['tenant'] ?? '');
        $canTenantManage = $tenantId !== '' && $auth->canDoInTenant($tenantId, 'manage_otp');

        if (!$isAppPrivileged && !$isOwner && !$canTenantManage) {
            flash('danger', 'Vous n\'avez pas la permission de supprimer ce code.');
            header('Location: /otp');
            exit;
        }

        try {
            $otpManager->delete($id, $currentUser['id']);
            flash('success', 'Code OTP mis à la corbeille.');
        } catch (\Exception $e) {
            flash('danger', 'Suppression OTP impossible : ' . $e->getMessage());
        }
    } else {
        flash('warning', 'Suppression OTP ignorée : identifiant manquant.');
    }
    header('Location: /otp');
    exit;
}

// ── Edit ─────────────────────────────────────────────────────────
if ($path === '/otp/edit' && $method === 'POST') {
    $id        = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $issuer    = trim($_POST['issuer'] ?? '');
    $algorithm = strtoupper(trim($_POST['algorithm'] ?? 'SHA1'));
    $digits    = (int)($_POST['digits'] ?? 6);
    $period    = (int)($_POST['period'] ?? 30);

    if (!in_array($algorithm, ['SHA1', 'SHA256', 'SHA512'], true)) {
        $algorithm = 'SHA1';
    }
    if (!in_array($digits, [6, 8], true)) {
        $digits = 6;
    }
    if ($period < 15 || $period > 120) {
        $period = 30;
    }
    if ($id && $name) {
        $record = $otpManager->getById($id);
        if (!$record) {
            flash('danger', 'Code introuvable.');
            header('Location: /otp');
            exit;
        }

        $isAppPrivileged = !empty($currentUser['is_app_admin']);
        $isOwner = (string)($record['owner'] ?? '') === (string)$currentUser['id'];
        $tenantId = (string)($record['tenant'] ?? '');
        $canTenantManage = $tenantId !== '' && $auth->canDoInTenant($tenantId, 'manage_otp');

        if (!$isAppPrivileged && !$isOwner && !$canTenantManage) {
            flash('danger', 'Vous n\'avez pas la permission de modifier ce code.');
            header('Location: /otp');
            exit;
        }

        $payload = [
            'name'      => $name,
            'issuer'    => $issuer,
            'algorithm' => $algorithm,
            'digits'    => $digits,
            'period'    => $period,
        ];

        $otpManager->update($id, $payload, $currentUser['id']);
        flash('success', 'Code OTP modifié.');
    }
    header('Location: /otp');
    exit;
}

// ── List (default) ───────────────────────────────────────────────
$pageTitle      = 'Codes OTP';
$userTenants    = $tenantManager->getUserTenants($currentUser['id']);
$otpWritableTenants = [];
$tenantManageOtpMap = [];
foreach ($userTenants as $tenant) {
    $canManageOtp = $auth->canDoInTenant((string)$tenant['id'], 'manage_otp');
    $tenantManageOtpMap[(string)$tenant['id']] = $canManageOtp;
    if ($canManageOtp) {
        $otpWritableTenants[] = $tenant;
    }
}
$canCreateOtp = !empty($currentUser['is_app_admin']) || !empty($otpWritableTenants) || !empty($currentUser['allow_personal_otp']);
$currentTenantId = $_SESSION['current_tenant'] ?? null;
$search          = trim($_GET['q'] ?? '');
$scopeParam      = strtolower(trim((string)($_GET['scope'] ?? 'all')));
$currentScope    = $scopeParam === 'personal' ? 'personal' : 'all';
$personalEnabled = !empty($currentUser['allow_personal_otp']);

// If a tenant is selected, tenant view takes precedence and personal codes stay hidden.
if (!empty($currentTenantId)) {
    $currentScope = 'all';
}

$showPersonalCodes = $personalEnabled && empty($currentTenantId);
$showTenantCodes   = $currentScope !== 'personal';

$personalCodes = $showPersonalCodes
    ? $otpManager->getPersonalCodes($currentUser['id'], $search)
    : [];
$tenantCodes   = [];
$currentTenantName = '';
if ($showTenantCodes && $currentTenantId) {
    $tenantCodes = $otpManager->getTenantCodes($currentTenantId, $search);
    foreach ($userTenants as $t) {
        if ($t['id'] === $currentTenantId) {
            $currentTenantName = $t['name'];
            break;
        }
    }
} elseif ($showTenantCodes) {
    // Show all tenants by default
    foreach ($userTenants as $t) {
        $codes = $otpManager->getTenantCodes($t['id'], $search);
        $tenantCodes = array_merge($tenantCodes, $codes);
    }
    $currentTenantName = 'Tous les tenants';
}

require __DIR__ . '/../templates/otp.php';
