<?php

/** @var string $path */
/** @var string $method */
/** @var \App\OTPManager $otpManager */
/** @var \App\TenantManager $tenantManager */
/** @var \App\Auth $auth */
/** @var array $currentUser */

function sanitizeOtpGroupId(string $value): string
{
    return preg_replace('/[^a-zA-Z0-9]/', '', $value);
}

function validateOtpGroupForTenant(\App\OTPManager $otpManager, string $groupId, string $tenantId): bool
{
    if ($groupId === '') {
        return true;
    }

    $group = $otpManager->getGroupById($groupId);
    if (!$group) {
        return false;
    }

    return (string)($group['tenant'] ?? '') === $tenantId;
}

function otpReturnPath(string $path): string
{
    return str_starts_with($path, '/otp') ? $path : '/otp';
}

// ── Créer un dossier de collection ─────────────────────────────
if ($path === '/otp/groups/create' && $method === 'POST') {
    $tenantId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $returnTo = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

    if ($tenantId === '' || !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', 'Vous n\'avez pas la permission de créer un dossier dans cette collection.');
        header('Location: ' . $returnTo);
        exit;
    }

    if ($name === '') {
        flash('warning', 'Le nom du dossier est obligatoire.');
        header('Location: ' . $returnTo);
        exit;
    }

    try {
        $otpManager->createGroup($tenantId, $name, (string)$currentUser['id']);
        flash('success', 'Dossier créé.');
    } catch (\Exception $e) {
        flash('danger', 'Création du dossier impossible : ' . $e->getMessage());
    }

    header('Location: ' . $returnTo);
    exit;
}

// ── Renommer un dossier de collection ──────────────────────────
if ($path === '/otp/groups/rename' && $method === 'POST') {
    $groupId  = sanitizeOtpGroupId((string)($_POST['group_id'] ?? ''));
    $newName  = trim((string)($_POST['name'] ?? ''));
    $returnTo = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

    if ($groupId === '') {
        flash('warning', 'Identifiant du dossier manquant.');
        header('Location: ' . $returnTo);
        exit;
    }

    if ($newName === '') {
        flash('warning', 'Le nouveau nom est obligatoire.');
        header('Location: ' . $returnTo);
        exit;
    }

    $group = $otpManager->getGroupById($groupId);
    if (!$group) {
        flash('danger', 'Dossier introuvable.');
        header('Location: ' . $returnTo);
        exit;
    }

    $tenantId = (string)($group['tenant'] ?? '');
    if ($tenantId === '' || !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', 'Vous n\'avez pas la permission de modifier ce dossier.');
        header('Location: ' . $returnTo);
        exit;
    }

    try {
        $otpManager->renameGroup($groupId, $tenantId, $newName);
        flash('success', 'Dossier renommé.');
    } catch (\Exception $e) {
        flash('danger', 'Renommage impossible : ' . $e->getMessage());
    }

    header('Location: ' . $returnTo);
    exit;
}

// ── Supprimer un dossier de collection ─────────────────────────
if ($path === '/otp/groups/delete' && $method === 'POST') {
    $groupId = sanitizeOtpGroupId((string)($_POST['group_id'] ?? ''));
    $returnTo = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

    if ($groupId === '') {
        flash('warning', 'Suppression du dossier ignorée : identifiant manquant.');
        header('Location: ' . $returnTo);
        exit;
    }

    $group = $otpManager->getGroupById($groupId);
    if (!$group) {
        flash('warning', 'Dossier introuvable.');
        header('Location: ' . $returnTo);
        exit;
    }

    $tenantId = (string)($group['tenant'] ?? '');
    if ($tenantId === '' || !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', 'Vous n\'avez pas la permission de supprimer ce dossier.');
        header('Location: ' . $returnTo);
        exit;
    }

    try {
        $movedCount = $otpManager->deleteGroup($groupId, $tenantId);
        flash('success', "Dossier supprimé. {$movedCount} code(s) déplacé(s) à la racine.");
    } catch (\Exception $e) {
        flash('danger', 'Suppression du dossier impossible : ' . $e->getMessage());
    }

    header('Location: ' . $returnTo);
    exit;
}

// ── Ajouter un code OTP ──────────────────────────────────────────
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
    $groupId    = sanitizeOtpGroupId((string)($_POST['group_id'] ?? ''));
    $returnTo   = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

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
        header('Location: ' . $returnTo);
        exit;
    }

    if (empty($currentUser['is_app_admin']) && !$canManageAnyTenantOtp && !$personalEnabled) {
        flash('danger', 'Votre role ne permet pas de creer de nouveaux codes OTP.');
        header('Location: ' . $returnTo);
        exit;
    }

    if ($name === '' || $secret === '') {
        flash('danger', 'Le nom et le secret sont obligatoires.');
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && $tenantId === '') {
        flash('danger', 'Veuillez sélectionner une collection pour un code non personnel.');
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', 'Vous n\'avez pas la permission de creer des codes OTP dans cette collection.');
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && !validateOtpGroupForTenant($otpManager, $groupId, $tenantId)) {
        flash('danger', 'Dossier invalide pour cette collection.');
        header('Location: ' . $returnTo);
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
        'group'       => $isPersonal ? '' : $groupId,
    ]);
    flash('success', 'Code OTP ajouté.');
    header('Location: ' . $returnTo);
    exit;
}

// ── Page d'import ────────────────────────────────────────────────
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
    // Vérifier que l'URI préremplie est une URI otpauth valide avant de la passer au template
    if ($prefillUri !== '' && !str_starts_with($prefillUri, 'otpauth://')) {
        $prefillUri = '';
    }
    require __DIR__ . '/../templates/import.php';
    return;
}

// ── Traitement de l'import ───────────────────────────────────────
if ($path === '/otp/import' && $method === 'POST') {
    $rawInput        = trim($_POST['otp_uri'] ?? '');
    $personalEnabled = !empty($currentUser['allow_personal_otp']);
    $requestedPersonal = !empty($_POST['is_personal']);
    $isPersonal      = $personalEnabled && $requestedPersonal;
    $tenantId        = $_POST['tenant'] ?? '';
    $groupId         = sanitizeOtpGroupId((string)($_POST['group_id'] ?? ''));
    $returnTo        = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

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
        header('Location: ' . $returnTo);
        exit;
    }

    if (empty($currentUser['is_app_admin']) && !$canManageAnyTenantOtp && !$personalEnabled) {
        flash('danger', 'Votre role ne permet pas de creer/importer des codes OTP.');
        header('Location: ' . $returnTo);
        exit;
    }

    // Extraire toutes les URI otpauth:// valides, une par ligne
    $uris = array_values(array_filter(
        array_map('trim', explode("\n", $rawInput)),
        fn($l) => str_starts_with($l, 'otpauth://')
    ));

    if (empty($uris)) {
        flash('danger', 'Aucune URI otpauth:// valide trouvée.');
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && $tenantId === '') {
        flash('danger', 'Veuillez sélectionner une collection pour un code non personnel.');
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && !$auth->canDoInTenant($tenantId, 'manage_otp')) {
        flash('danger', "Vous n'avez pas la permission d'importer des codes OTP dans cette collection.");
        header('Location: ' . $returnTo);
        exit;
    }

    if (!$isPersonal && !validateOtpGroupForTenant($otpManager, $groupId, $tenantId)) {
        flash('danger', 'Dossier invalide pour cette collection.');
        header('Location: ' . $returnTo);
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
        $parsed['group']       = $isPersonal ? '' : $groupId;
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
    header('Location: ' . $returnTo);
    exit;
}

// ── Page d'export ────────────────────────────────────────────────
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

// ── Soumission du formulaire d'export ────────────────────────────
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

// ── Suppression (logique) ────────────────────────────────────────
if ($path === '/otp/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    $returnTo = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));
    if ($id) {
        $record = $otpManager->getById($id);
        if (!$record) {
            flash('danger', 'Code introuvable.');
            header('Location: ' . $returnTo);
            exit;
        }

        $isAppPrivileged = !empty($currentUser['is_app_admin']);
        $isOwner = (string)($record['owner'] ?? '') === (string)$currentUser['id'];
        $tenantId = (string)($record['tenant'] ?? '');
        $canTenantManage = $tenantId !== '' && $auth->canDoInTenant($tenantId, 'manage_otp');

        if (!$isAppPrivileged && !$isOwner && !$canTenantManage) {
            flash('danger', 'Vous n\'avez pas la permission de supprimer ce code.');
            header('Location: ' . $returnTo);
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
    header('Location: ' . $returnTo);
    exit;
}

// ── Modifier ─────────────────────────────────────────────────────
if ($path === '/otp/edit' && $method === 'POST') {
    $id        = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $issuer    = trim($_POST['issuer'] ?? '');
    $algorithm = strtoupper(trim($_POST['algorithm'] ?? 'SHA1'));
    $digits    = (int)($_POST['digits'] ?? 6);
    $period    = (int)($_POST['period'] ?? 30);
    $groupId   = sanitizeOtpGroupId((string)($_POST['group_id'] ?? ''));
    $returnTo  = otpReturnPath((string)($_POST['return_to'] ?? '/otp'));

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
            header('Location: ' . $returnTo);
            exit;
        }

        $isAppPrivileged = !empty($currentUser['is_app_admin']);
        $isOwner = (string)($record['owner'] ?? '') === (string)$currentUser['id'];
        $tenantId = (string)($record['tenant'] ?? '');
        $canTenantManage = $tenantId !== '' && $auth->canDoInTenant($tenantId, 'manage_otp');

        if (!$isAppPrivileged && !$isOwner && !$canTenantManage) {
            flash('danger', 'Vous n\'avez pas la permission de modifier ce code.');
            header('Location: ' . $returnTo);
            exit;
        }

        if (!empty($record['is_personal'])) {
            $groupId = '';
        } elseif (!validateOtpGroupForTenant($otpManager, $groupId, $tenantId)) {
            flash('danger', 'Dossier invalide pour cette collection.');
            header('Location: ' . $returnTo);
            exit;
        }

        $payload = [
            'name'      => $name,
            'issuer'    => $issuer,
            'algorithm' => $algorithm,
            'digits'    => $digits,
            'period'    => $period,
            'group'     => $groupId,
        ];

        $otpManager->update($id, $payload, $currentUser['id']);
        flash('success', 'Code OTP modifié.');
    }
    header('Location: ' . $returnTo);
    exit;
}

// ── Lister (par défaut) ──────────────────────────────────────────
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
$folderParam     = sanitizeOtpGroupId((string)($_GET['folder'] ?? $_GET['group'] ?? ''));
$scopeParam      = strtolower(trim((string)($_GET['scope'] ?? 'all')));
$currentScope    = $scopeParam === 'personal' ? 'personal' : 'all';
$personalEnabled = !empty($currentUser['allow_personal_otp']);
$tenantGroups    = [];
$tenantFolders   = [];
$currentFolderId = '';
$currentFolderName = '';
$currentTenantCanManageOtp = false;
$rootTenantCodes = [];

// Si une collection est sélectionnée, la vue collection est prioritaire et masque les codes personnels.
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
    $currentTenantCanManageOtp = $auth->canDoInTenant((string)$currentTenantId, 'manage_otp');
    $tenantGroups = $otpManager->getTenantGroups((string)$currentTenantId);
    $groupIds = array_map(static fn(array $group): string => (string)($group['id'] ?? ''), $tenantGroups);
    if ($folderParam !== '' && in_array($folderParam, $groupIds, true)) {
        $currentFolderId = $folderParam;
    }

    $folderSummary = $otpManager->getTenantFolderSummary((string)$currentTenantId);
    $tenantFolders = $folderSummary['folders'] ?? [];
    $rootTenantCodes = $otpManager->getTenantCodesByGroup((string)$currentTenantId, '', $search);
    $tenantCodes = $currentFolderId !== ''
        ? $otpManager->getTenantCodesByGroup((string)$currentTenantId, $currentFolderId, $search)
        : $rootTenantCodes;

    foreach ($userTenants as $t) {
        if ($t['id'] === $currentTenantId) {
            $currentTenantName = $t['name'];
            break;
        }
    }
    foreach ($tenantFolders as $folder) {
        if ((string)($folder['id'] ?? '') === $currentFolderId) {
            $currentFolderName = (string)($folder['name'] ?? 'Dossier');
            break;
        }
    }
} elseif ($showTenantCodes) {
    // Afficher toutes les collections par défaut
    foreach ($userTenants as $t) {
        $codes = $otpManager->getTenantCodes($t['id'], $search);
        $tenantCodes = array_merge($tenantCodes, $codes);
    }
    $currentTenantName = 'Toutes les collections';
}

require __DIR__ . '/../templates/otp.php';
