<?php

/** @var string $path */
/** @var string $method */
/** @var \App\TenantManager $tenantManager */
/** @var \App\PocketBaseClient $pb */
/** @var \App\OTPManager $otpManager */
/** @var \App\AuditLogger $auditLogger */
/** @var \App\SecurityLogger $securityLogger */
/** @var array $config */
/** @var array $currentUser */

use App\PermissionManager;
use App\RuntimeSettings;

// Réservé aux administrateurs de l'application
if (empty($currentUser['is_app_admin'])) {
    flash('danger', 'Accès refusé.');
    header('Location: /');
    exit;
}

// ── Créer un utilisateur ─────────────────────────────────────────
if ($path === '/admin/users/create' && $method === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';
    $isAdmin  = !empty($_POST['is_app_admin']);

    if ($email === '' || $password === '') {
        flash('danger', 'Email et mot de passe sont obligatoires.');
        header('Location: /admin/users');
        exit;
    }

    try {
        $pb->createRecord('users', [
            'email'           => $email,
            'password'        => $password,
            'passwordConfirm' => $password,
            'name'            => $name,
            'is_app_admin'    => $isAdmin,
            'allow_personal_otp' => false,
            'is_ad_user'      => false,
            'ad_username'     => '',
        ]);
        flash('success', 'Utilisateur créé.');
    } catch (\Exception $e) {
        flash('danger', 'Erreur : ' . $e->getMessage());
    }
    header('Location: /admin/users');
    exit;
}

// ── Basculer le statut admin ─────────────────────────────────────
if ($path === '/admin/users/toggle-admin' && $method === 'POST') {
    $uid     = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $isAdmin = !empty($_POST['is_app_admin']);
    if ($uid) {
        if ($uid === (string)($currentUser['id'] ?? '')) {
            flash('danger', 'Vous ne pouvez pas modifier votre propre role global.');
            header('Location: /admin/users');
            exit;
        }
        $pb->updateRecord('users', $uid, ['is_app_admin' => $isAdmin]);
        flash('success', 'Statut administrateur mis à jour.');
    }
    header('Location: /admin/users');
    exit;
}

// ── Basculer la permission OTP personnel ────────────────────────
if ($path === '/admin/users/toggle-personal-otp' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $allowPersonalOtp = !empty($_POST['allow_personal_otp']);

    if ($uid) {
        $pb->updateRecord('users', $uid, ['allow_personal_otp' => $allowPersonalOtp]);
        if ($uid === (string)($currentUser['id'] ?? '')) {
            $_SESSION['user']['allow_personal_otp'] = $allowPersonalOtp;
        }
        flash(
            'success',
            $allowPersonalOtp
                ? 'OTP personnels autorisés pour cet utilisateur.'
                : 'OTP personnels désactivés pour cet utilisateur.'
        );
    }

    header('Location: /admin/users');
    exit;
}

// ── Supprimer un utilisateur ─────────────────────────────────────
if ($path === '/admin/users/delete' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    if ($uid && $uid !== $currentUser['id']) {
        $pb->deleteRecord('users', $uid);
        flash('success', 'Utilisateur supprimé.');
    }
    header('Location: /admin/users');
    exit;
}

// ── Modifier un utilisateur ──────────────────────────────────────
if ($path === '/admin/users/edit' && $method === 'POST') {
    $uid      = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($uid && $email !== '') {
        $existingUser = $pb->getRecord('users', $uid);
        $isFederated = !empty($existingUser['is_ad_user']) || !empty($existingUser['is_oidc_user']);
        $data = ['email' => $email, 'name' => $name];
        if ($password !== '') {
            if ($isFederated) {
                flash('danger', 'Mot de passe non modifiable pour un compte AD/OIDC.');
                header('Location: /admin/users');
                exit;
            }
            $data['password']        = $password;
            $data['passwordConfirm'] = $password;
        }
        try {
            $pb->updateRecord('users', $uid, $data);
            flash('success', 'Utilisateur modifié.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users');
    exit;
}

// ── Gérer les appartenances utilisateur aux collections ─────────
if ($path === '/admin/users/tenants/add' && $method === 'POST') {
    $uid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['user_id'] ?? '');
    $tid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['tenant_id'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'viewer'));

    if (!PermissionManager::isValidRole($role) || $role === 'owner') {
        $role = 'viewer';
    }

    if ($uid && $tid) {
        try {
            $tenantManager->addMemberById($tid, $uid, $role);
            flash('success', 'Collection ajoutée à l\'utilisateur.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

if ($path === '/admin/users/tenants/update' && $method === 'POST') {
    $mid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $role = trim((string)($_POST['role'] ?? 'viewer'));
    $uid = '';

    if ($mid) {
        $membership = $tenantManager->getMembershipById($mid);
        $currentRole = (string)($membership['role'] ?? '');
        $uid = (string)($membership['user'] ?? '');

        if ($uid === (string)($currentUser['id'] ?? '')) {
            flash('danger', 'Vous ne pouvez pas modifier votre propre role de collection.');
            header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
            exit;
        }

        if ($currentRole === 'owner') {
            flash('danger', 'Le rôle propriétaire ne peut pas être modifié ici.');
            header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
            exit;
        }

        if (!PermissionManager::isValidRole($role) || $role === 'owner') {
            $role = 'viewer';
        }

        try {
            $tenantManager->updateMemberRole($mid, $role);
            flash('success', 'Rôle collection mis à jour.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

if ($path === '/admin/users/tenants/remove' && $method === 'POST') {
    $mid = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['membership_id'] ?? '');
    $uid = '';
    if ($mid) {
        $membership = $tenantManager->getMembershipById($mid);
        $uid = (string)($membership['user'] ?? '');
        if (($membership['role'] ?? '') === 'owner') {
            flash('danger', 'Le propriétaire ne peut pas être retiré ici.');
            header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
            exit;
        }

        try {
            $tenantManager->removeMember($mid);
            flash('success', 'Utilisateur retiré de la collection.');
        } catch (\Exception $e) {
            flash('danger', 'Erreur : ' . $e->getMessage());
        }
    }
    header('Location: /admin/users?open_tenant_user=' . urlencode($uid));
    exit;
}

// ── Lister les utilisateurs (par défaut) ────────────────────────
if ($path === '/admin/users') {
    $pageTitle = 'Gestion des utilisateurs';
    $openTenantUserId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['open_tenant_user'] ?? '');
    $allUsers  = $tenantManager->getAllUsers();
    $allTenants = $tenantManager->getAllTenants();
    $membershipsByUser = [];
    foreach ($allUsers as $u) {
        $uid = (string)($u['id'] ?? '');
        if ($uid === '') {
            continue;
        }
        $membershipsByUser[$uid] = $tenantManager->getUserMemberships($uid);
    }
    require __DIR__ . '/../templates/admin-users.php';
    return;
}

if ($path === '/admin/health' && $method === 'GET') {
    $pageTitle = 'Santé applicative';
    $runtimeSettings = new RuntimeSettings($pb);

    $pbStart = microtime(true);
    $pocketbaseStatus = ['ok' => false, 'message' => 'Inconnu'];
    try {
        $ok = $pb->getCollection('users') !== null;
        $pocketbaseStatus = [
            'ok' => $ok,
            'message' => $ok ? 'Connecté' : 'Non joignable',
        ];
    } catch (\Exception $e) {
        $pocketbaseStatus = ['ok' => false, 'message' => $e->getMessage()];
    }
    $pocketbaseLatencyMs = (int)round((microtime(true) - $pbStart) * 1000);

    $latencyMetrics = $runtimeSettings->getJson('health_pocketbase_latency', []);
    $latencySamples = $latencyMetrics['samples'] ?? [];
    if (!is_array($latencySamples)) {
        $latencySamples = [];
    }
    $latencySamples[] = ['at' => date('c'), 'ms' => $pocketbaseLatencyMs];
    $latencySamples = array_slice($latencySamples, -10);
    $latencyAvgMs = 0;
    if (!empty($latencySamples)) {
        $latencyAvgMs = (int)round(array_sum(array_map(static fn(array $s): int => (int)($s['ms'] ?? 0), $latencySamples)) / count($latencySamples));
    }
    $runtimeSettings->setJson('health_pocketbase_latency', ['samples' => $latencySamples]);

    $ldapEnabled = !empty($config['ldap']['enabled']);
    $ldapConfigured = !empty($config['ldap']['host']) && !empty($config['ldap']['base_dn']);
    $ldapReachable = null;
    if ($ldapEnabled && $ldapConfigured && extension_loaded('ldap')) {
        $protocol = !empty($config['ldap']['use_ssl']) ? 'ldaps://' : 'ldap://';
        $uri = $protocol . $config['ldap']['host'] . ':' . (int)($config['ldap']['port'] ?? 389);
        $conn = @ldap_connect($uri);
        if ($conn !== false) {
            @ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            @ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            @ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3);

            $bindDn = trim((string)($config['ldap']['bind_dn'] ?? ''));
            $bindPassword = (string)($config['ldap']['bind_password'] ?? '');
            if ($bindDn !== '') {
                $ldapReachable = @ldap_bind($conn, $bindDn, $bindPassword);
            } else {
                $ldapReachable = @ldap_bind($conn);
            }
            @ldap_unbind($conn);
        } else {
            $ldapReachable = false;
        }
    }

    $oidcEnabled = !empty($config['oidc']['enabled']);
    $oidcConfigured = !empty($config['oidc']['provider_url'])
        && !empty($config['oidc']['client_id'])
        && !empty($config['oidc']['redirect_uri']);

    $otpCount = 0;
    try {
        $result = $pb->listRecords('otp_codes', [
            'filter' => 'deleted!=true',
            'perPage' => 1,
            'page' => 1,
        ]);
        $otpCount = (int)($result['totalItems'] ?? 0);
    } catch (\Exception) {
        $otpCount = 0;
    }

    $pocketbaseBackupCount = 0;
    try {
        $backups = $pb->listBackups();
        $pocketbaseBackupCount = count(is_array($backups) ? $backups : []);
    } catch (\Exception) {
        $pocketbaseBackupCount = 0;
    }

    $authFailures = $securityLogger->latestFailures(10);

    $authFailureWindow = ['5m' => 0, '1h' => 0, '24h' => 0];
    $sessionTimeouts24h = 0;
    try {
        $windows = ['5m' => 300, '1h' => 3600, '24h' => 86400];
        foreach ($windows as $k => $seconds) {
            $since = gmdate('c', time() - $seconds);
            $result = $pb->listRecords('auth_failures', [
                'filter' => 'occurred_at >= "' . $since . '"',
                'perPage' => 1,
                'page' => 1,
            ]);
            $authFailureWindow[$k] = (int)($result['totalItems'] ?? 0);
        }

        $since24h = gmdate('c', time() - 86400);
        $timeouts = $pb->listRecords('auth_failures', [
            'filter' => 'occurred_at >= "' . $since24h . '" && reason = "session_timeout"',
            'perPage' => 1,
            'page' => 1,
        ]);
        $sessionTimeouts24h = (int)($timeouts['totalItems'] ?? 0);
    } catch (\Exception) {
        // Keep zero values if PocketBase filter query fails.
    }

    $sessionHealth = [
        'active' => 0,
        'files_total' => 0,
        'timeout_seconds' => max(60, (int)($config['session_timeout_seconds'] ?? 900)),
        'expired_24h' => $sessionTimeouts24h,
    ];
    $sessionPath = session_save_path();
    if ($sessionPath === '') {
        $sessionPath = sys_get_temp_dir();
    }
    if (is_dir($sessionPath)) {
        $sessionFiles = glob(rtrim($sessionPath, '/') . '/sess_*') ?: [];
        $sessionHealth['files_total'] = count($sessionFiles);
        $nowTs = time();
        foreach ($sessionFiles as $sf) {
            $mtime = @filemtime($sf);
            if ($mtime !== false && ($nowTs - $mtime) <= $sessionHealth['timeout_seconds']) {
                $sessionHealth['active']++;
            }
        }
    }

    $healthEvents = [];
    if (empty($pocketbaseStatus['ok'])) {
        $healthEvents[] = [
            'at' => date('c'),
            'level' => 'danger',
            'source' => 'PocketBase',
            'message' => (string)($pocketbaseStatus['message'] ?? 'Non joignable'),
        ];
    }

    foreach (array_slice($securityLogger->latestFailures(20), 0, 10) as $failure) {
        $healthEvents[] = [
            'at' => (string)($failure['occurred_at'] ?? date('c')),
            'level' => 'info',
            'source' => 'Auth',
            'message' => (string)($failure['reason'] ?? 'failure') . ' (' . (string)($failure['identity'] ?? '-') . ')',
        ];
    }

    usort($healthEvents, static fn(array $a, array $b): int => strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? '')));
    $healthEvents = array_slice($healthEvents, 0, 20);

    require __DIR__ . '/../templates/admin-health.php';
    return;
}

if ($path === '/admin/backup' && $method === 'GET') {
    $pageTitle = 'Sauvegardes PocketBase';
    $backupItems = [];
    try {
        $items = $pb->listBackups();
        if (is_array($items)) {
            $backupItems = $items;
        }
    } catch (\Exception $e) {
        flash('danger', 'Impossible de lister les sauvegardes PocketBase : ' . $e->getMessage());
    }

    usort($backupItems, static function (array $a, array $b): int {
        $ad = (string)($a['created'] ?? $a['modified'] ?? $a['updated'] ?? '');
        $bd = (string)($b['created'] ?? $b['modified'] ?? $b['updated'] ?? '');
        return strcmp($bd, $ad);
    });

    require __DIR__ . '/../templates/admin-backup.php';
    return;
}

// ── Corbeille : lister les codes supprimés ──────────────────────
if ($path === '/admin/trash' && $method === 'GET') {
    $pageTitle   = 'Corbeille';
    $trashedCodes = $otpManager->getTrashedCodes();
    $trashedTenants = $tenantManager->getTrashedTenants();
    require __DIR__ . '/../templates/admin-trash.php';
    return;
}

// ── Corbeille : restaurer un code ────────────────────────────────
if ($path === '/admin/trash/restore' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $record = $otpManager->getById($id);
        $otpManager->restore($id);
        $auditLogger->log('otp', 'restore', $currentUser, [
            'target_id' => $id,
            'target_name' => (string)($record['name'] ?? ''),
            'tenant' => (string)($record['tenant'] ?? ''),
            'details' => ['is_personal' => !empty($record['is_personal'])],
        ]);
        flash('success', 'Code OTP restauré.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille : suppression définitive ──────────────────────────
if ($path === '/admin/trash/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $record = $otpManager->getById($id);
        $otpManager->permanentDelete($id);
        $auditLogger->log('otp', 'permanent_delete', $currentUser, [
            'target_id' => $id,
            'target_name' => (string)($record['name'] ?? ''),
            'tenant' => (string)($record['tenant'] ?? ''),
            'details' => ['is_personal' => !empty($record['is_personal'])],
        ]);
        flash('success', 'Code OTP définitivement supprimé.');
    }
    header('Location: /admin/trash');
    exit;
}

if ($path === '/admin/audit' && $method === 'GET') {
    $pageTitle = 'Journal d\'audit';
    $auditItems = $auditLogger->latest(300);
    require __DIR__ . '/../templates/admin-audit.php';
    return;
}

if ($path === '/admin/auth-failures' && $method === 'GET') {
    $pageTitle = 'Derniers échecs d\'authentification';
    $authFailures = $securityLogger->latestFailures(300);
    require __DIR__ . '/../templates/admin-auth-failures.php';
    return;
}

// ── Corbeille collections : restaurer ───────────────────────────
if ($path === '/admin/trash/tenant/restore' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $tenantManager->restore($id);
        flash('success', 'Collection restaurée.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille collections : suppression définitive ──────────────
if ($path === '/admin/trash/tenant/delete' && $method === 'POST') {
    $id = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['id'] ?? '');
    if ($id) {
        $tenantManager->permanentDelete($id);
        flash('success', 'Collection définitivement supprimée.');
    }
    header('Location: /admin/trash');
    exit;
}

// ── Corbeille : tout vider ──────────────────────────────────────
if ($path === '/admin/trash/empty' && $method === 'POST') {
    $trashedCodes = $otpManager->getTrashedCodes();
    foreach ($trashedCodes as $code) {
        $otpManager->permanentDelete($code['id']);
    }
    $trashedTenants = $tenantManager->getTrashedTenants();
    foreach ($trashedTenants as $tenant) {
        $tenantManager->permanentDelete((string)$tenant['id']);
    }
    flash('success', 'Corbeille vidée.');
    header('Location: /admin/trash');
    exit;
}

http_response_code(404);
$pageTitle = 'Page introuvable';
require __DIR__ . '/../templates/404.php';
