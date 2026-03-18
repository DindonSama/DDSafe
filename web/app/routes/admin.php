<?php

/** @var string $path */
/** @var string $method */
/** @var \App\TenantManager $tenantManager */
/** @var \App\PocketBaseClient $pb */
/** @var \App\OTPManager $otpManager */
/** @var \App\AuditLogger $auditLogger */
/** @var \App\SecurityLogger $securityLogger */
/** @var \App\BackupExporter $backupExporter */
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
        $ldapReachable = $conn !== false;
        if ($conn !== false) {
            @ldap_unbind($conn);
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

    $authFailures = $securityLogger->latestFailures(10);

    $defaultScheduler = $config['backup_scheduler'] ?? [];
    $schedulerOutputDir = (string)($defaultScheduler['output_dir'] ?? '/backups');

    $backupDisk = [
        'path' => $schedulerOutputDir,
        'total' => 0,
        'free' => 0,
        'used_pct' => 0,
        'free_pct' => 0,
        'alert' => false,
    ];
    if (is_dir($schedulerOutputDir)) {
        $diskTotal = @disk_total_space($schedulerOutputDir);
        $diskFree = @disk_free_space($schedulerOutputDir);
        if ($diskTotal !== false && $diskFree !== false && $diskTotal > 0) {
            $backupDisk['total'] = (int)$diskTotal;
            $backupDisk['free'] = (int)$diskFree;
            $backupDisk['free_pct'] = (int)round(($diskFree / $diskTotal) * 100);
            $backupDisk['used_pct'] = 100 - $backupDisk['free_pct'];
            $backupDisk['alert'] = $backupDisk['free_pct'] < 15;
        }
    }

    $schedulerConfigHealth = array_merge($defaultScheduler, $runtimeSettings->getJson('backup_scheduler', []));
    $schedulerStateHealth = [];
    $schedulerStateFile = rtrim($schedulerOutputDir, '/') . '/.backup-scheduler-state.json';
    if (is_file($schedulerStateFile)) {
        $rawSchedulerState = (string)@file_get_contents($schedulerStateFile);
        $decodedSchedulerState = json_decode($rawSchedulerState, true);
        if (is_array($decodedSchedulerState)) {
            $schedulerStateHealth = $decodedSchedulerState;
        }
    }

    $nextRunBySchedule = [];
    $runHour = max(0, min(23, (int)($schedulerConfigHealth['run_hour'] ?? 2)));
    $weeklyDay = max(1, min(7, (int)($schedulerConfigHealth['weekly_day'] ?? 7)));
    $monthlyDay = max(1, min(31, (int)($schedulerConfigHealth['monthly_day'] ?? 1)));
    $enabledSchedules = array_values(array_filter(array_map('trim', explode(',', (string)($schedulerConfigHealth['schedules'] ?? 'daily,weekly,monthly')))));
    $now = new \DateTimeImmutable('now');

    foreach (['daily', 'weekly', 'monthly'] as $schedule) {
        if (!in_array($schedule, $enabledSchedules, true)) {
            $nextRunBySchedule[$schedule] = null;
            continue;
        }

        if ($schedule === 'daily') {
            $candidate = $now->setTime($runHour, 0, 0);
            if ($candidate <= $now) {
                $candidate = $candidate->modify('+1 day');
            }
            $nextRunBySchedule[$schedule] = $candidate->format('c');
            continue;
        }

        if ($schedule === 'weekly') {
            $currentIsoDay = (int)$now->format('N');
            $delta = $weeklyDay - $currentIsoDay;
            if ($delta < 0) {
                $delta += 7;
            }
            $candidate = $now->modify('+' . $delta . ' day')->setTime($runHour, 0, 0);
            if ($candidate <= $now) {
                $candidate = $candidate->modify('+7 day');
            }
            $nextRunBySchedule[$schedule] = $candidate->format('c');
            continue;
        }

        $year = (int)$now->format('Y');
        $month = (int)$now->format('n');
        $daysInMonth = (int)$now->format('t');
        $day = min($monthlyDay, $daysInMonth);
        $candidate = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $runHour)));
        if ($candidate <= $now) {
            $nextMonth = $now->modify('first day of next month');
            $ny = (int)$nextMonth->format('Y');
            $nm = (int)$nextMonth->format('n');
            $nd = (int)$nextMonth->format('t');
            $day = min($monthlyDay, $nd);
            $candidate = (new \DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:00:00', $ny, $nm, $day, $runHour)));
        }
        $nextRunBySchedule[$schedule] = $candidate->format('c');
    }

    $backupCount = 0;
    if (is_dir($schedulerOutputDir)) {
        foreach (scandir($schedulerOutputDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            if (is_file($schedulerOutputDir . '/' . $entry)
                && preg_match('/^ddsafe-(auto|backup)-.*\.json$/', $entry)) {
                $backupCount++;
            }
        }
    }

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

    $backupIntegrity = [
        'status' => 'unknown',
        'message' => 'Aucune sauvegarde disponible.',
        'file' => null,
        'checked_at' => date('c'),
    ];
    if (is_dir($schedulerOutputDir)) {
        $latestFile = null;
        $latestMtime = 0;
        foreach (scandir($schedulerOutputDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            if (!preg_match('/^ddsafe-(auto|backup)-.*\.json$/', $entry)) {
                continue;
            }
            $fp = $schedulerOutputDir . '/' . $entry;
            if (!is_file($fp)) {
                continue;
            }
            $mt = (int)(filemtime($fp) ?: 0);
            if ($mt >= $latestMtime) {
                $latestMtime = $mt;
                $latestFile = $fp;
            }
        }

        if ($latestFile !== null) {
            $backupIntegrity['file'] = basename($latestFile);
            try {
                $raw = (string)file_get_contents($latestFile);
                $isEncrypted = str_ends_with($latestFile, '.enc.json');
                $passphrase = (string)($schedulerConfigHealth['passphrase'] ?? '');
                if ($isEncrypted && strlen($passphrase) < 8) {
                    $backupIntegrity['status'] = 'warning';
                    $backupIntegrity['message'] = 'Backup chiffré détecté mais passphrase scheduler absente/invalide.';
                } else {
                    $payload = $backupExporter->decodeBackup($raw, $isEncrypted ? $passphrase : '');
                    $summary = $backupExporter->verifyPayload($payload);
                    $stats = $summary['stats'] ?? [];
                    $backupIntegrity['status'] = 'ok';
                    $backupIntegrity['message'] = 'Backup valide (' . (int)($stats['otp_codes'] ?? 0) . ' OTP).';
                }
            } catch (\Exception $e) {
                $backupIntegrity['status'] = 'error';
                $backupIntegrity['message'] = 'Backup illisible/invalide: ' . $e->getMessage();
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

    foreach (['daily', 'weekly', 'monthly'] as $schedule) {
        $state = $schedulerStateHealth[$schedule] ?? [];
        if (($state['last_status'] ?? '') === 'error') {
            $healthEvents[] = [
                'at' => (string)($state['last_error_at'] ?? date('c')),
                'level' => 'warning',
                'source' => 'Scheduler ' . $schedule,
                'message' => (string)($state['last_error'] ?? 'Erreur inconnue'),
            ];
        }
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

if ($path === '/admin/backup/scheduler' && $method === 'POST') {
    $runtimeSettings = new RuntimeSettings($pb);

    $enabled = !empty($_POST['enabled']);
    $selectedSchedules = $_POST['schedules'] ?? [];
    if (!is_array($selectedSchedules)) {
        $selectedSchedules = [];
    }

    $allowedSchedules = ['daily', 'weekly', 'monthly'];
    $normalizedSchedules = [];
    foreach ($selectedSchedules as $schedule) {
        $schedule = strtolower(trim((string)$schedule));
        if (in_array($schedule, $allowedSchedules, true)) {
            $normalizedSchedules[] = $schedule;
        }
    }
    $normalizedSchedules = array_values(array_unique($normalizedSchedules));

    if (empty($normalizedSchedules)) {
        flash('danger', 'Selectionnez au moins une periode (daily/weekly/monthly).');
        header('Location: /admin/backup');
        exit;
    }

    $exportMode = strtolower(trim((string)($_POST['export_mode'] ?? 'encrypted')));
    if (!in_array($exportMode, ['encrypted', 'plain'], true)) {
        $exportMode = 'encrypted';
    }

    $passphrase = (string)($_POST['passphrase'] ?? '');
    if ($exportMode === 'encrypted' && strlen($passphrase) < 8) {
        flash('danger', 'La phrase de passe doit contenir au moins 8 caracteres en mode chiffre.');
        header('Location: /admin/backup');
        exit;
    }

    $payload = [
        'enabled' => $enabled,
        'schedules' => implode(',', $normalizedSchedules),
        'run_hour' => max(0, min(23, (int)($_POST['run_hour'] ?? 2))),
        'weekly_day' => max(1, min(7, (int)($_POST['weekly_day'] ?? 7))),
        'monthly_day' => max(1, min(31, (int)($_POST['monthly_day'] ?? 1))),
        'export_mode' => $exportMode,
        'include_secrets' => !empty($_POST['include_secrets']),
        'passphrase' => $passphrase,
        'retention_daily' => max(1, (int)($_POST['retention_daily'] ?? 14)),
        'retention_weekly' => max(1, (int)($_POST['retention_weekly'] ?? 8)),
        'retention_monthly' => max(1, (int)($_POST['retention_monthly'] ?? 12)),
        'check_interval_seconds' => max(60, (int)($_POST['check_interval_seconds'] ?? 300)),
    ];

    try {
        $runtimeSettings->setJson('backup_scheduler', $payload);
        $auditLogger->log('admin', 'backup_scheduler_update', $currentUser, [
            'target_name' => 'Backup scheduler runtime config',
            'details' => array_merge($payload, ['passphrase' => $passphrase !== '' ? '***' : '']),
        ]);
        flash('success', 'Configuration du backup scheduler mise a jour.');
    } catch (\Exception $e) {
        flash('danger', 'Impossible de mettre a jour la configuration: ' . $e->getMessage());
    }

    header('Location: /admin/backup');
    exit;
}

if ($path === '/admin/backup' && $method === 'GET') {
    $pageTitle = 'Sauvegarde / Export admin';

    $schedulerOutputDir = rtrim((string)(($config['backup_scheduler'] ?? [])['output_dir'] ?? '/backups'), '/');
    $backupFiles = [];
    if (is_dir($schedulerOutputDir)) {
        foreach (scandir($schedulerOutputDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $fp = $schedulerOutputDir . '/' . $entry;
            if (!is_file($fp)) {
                continue;
            }
            if (!preg_match('/^ddsafe-(auto|backup)-.*\.json$/', $entry)) {
                continue;
            }
            $isEncrypted = str_ends_with($entry, '.enc.json');
            $btype    = 'manual';
            $schedule = '-';
            if (preg_match('/^ddsafe-auto-(daily|weekly|monthly)-/', $entry, $m)) {
                $btype    = 'auto';
                $schedule = $m[1];
            }
            $backupFiles[] = [
                'name'     => $entry,
                'type'     => $btype,
                'schedule' => $schedule,
                'mode'     => $isEncrypted ? 'encrypted' : 'plain',
                'size'     => (int)filesize($fp),
                'mtime'    => (int)filemtime($fp),
            ];
        }
        usort($backupFiles, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    }

    $runtimeSettingsForBackup  = new RuntimeSettings($pb);
    $defaultSchedulerForBackup = $config['backup_scheduler'] ?? [];
    $schedulerConfigForBackup  = array_merge(
        $defaultSchedulerForBackup,
        $runtimeSettingsForBackup->getJson('backup_scheduler', [])
    );

    $schedulerStateForBackup = [];
    $schedulerOutputDirForState = rtrim((string)($defaultSchedulerForBackup['output_dir'] ?? '/backups'), '/');
    $schedulerStateFileForBackup = $schedulerOutputDirForState . '/.backup-scheduler-state.json';
    if (is_file($schedulerStateFileForBackup)) {
        $rawSchedulerState = (string)@file_get_contents($schedulerStateFileForBackup);
        $decodedSchedulerState = json_decode($rawSchedulerState, true);
        if (is_array($decodedSchedulerState)) {
            $schedulerStateForBackup = $decodedSchedulerState;
        }
    }

    require __DIR__ . '/../templates/admin-backup.php';
    return;
}

// ── Backup manuel : sauvegarde sur le serveur ───────────────────────────────
if ($path === '/admin/backup/save' && $method === 'POST') {
    $mode           = strtolower(trim((string)($_POST['export_mode'] ?? 'encrypted')));
    $passphrase     = (string)($_POST['passphrase'] ?? '');
    $includeSecrets = !empty($_POST['include_secrets']);

    if (!in_array($mode, ['encrypted', 'plain'], true)) {
        $mode = 'encrypted';
    }
    if ($mode === 'encrypted' && strlen($passphrase) < 8) {
        flash('danger', 'La phrase de passe doit contenir au moins 8 caract\u00e8res.');
        header('Location: /admin/backup');
        exit;
    }

    $schedulerOutputDir = rtrim((string)(($config['backup_scheduler'] ?? [])['output_dir'] ?? '/backups'), '/');
    if (!is_dir($schedulerOutputDir)) {
        @mkdir($schedulerOutputDir, 0775, true);
    }
    if (!is_dir($schedulerOutputDir)) {
        flash('danger', 'Dossier de sauvegardes inaccessible : ' . htmlspecialchars($schedulerOutputDir));
        header('Location: /admin/backup');
        exit;
    }

    try {
        $payload  = $backupExporter->buildPayload($config, $includeSecrets);
        $suffix   = $includeSecrets ? 'full' : 'metadata';
        $timestamp = date('Ymd-His');

        if ($mode === 'encrypted') {
            $body     = $backupExporter->encryptPayload($payload, $passphrase);
            $filename = 'ddsafe-backup-' . $timestamp . '-' . $suffix . '.enc.json';
        } else {
            $body     = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new \RuntimeException('Encodage JSON impossible.');
            }
            $filename = 'ddsafe-backup-' . $timestamp . '-' . $suffix . '.json';
        }

        $dest = $schedulerOutputDir . '/' . $filename;
        if (file_put_contents($dest, $body) === false) {
            throw new \RuntimeException('\u00c9criture impossible : ' . $dest);
        }

        $auditLogger->log('admin', 'backup_manual_save', $currentUser, [
            'target_name' => $filename,
            'details'     => [
                'mode'            => $mode,
                'include_secrets' => $includeSecrets,
                'size'            => strlen($body),
            ],
        ]);

        flash('success', 'Sauvegarde enregistr\u00e9e\u00a0: ' . $filename);
    } catch (\Exception $e) {
        flash('danger', 'Sauvegarde impossible\u00a0: ' . $e->getMessage());
    }

    header('Location: /admin/backup');
    exit;
}

if ($path === '/admin/backup/export' && $method === 'POST') {
    $mode = (string)($_POST['export_mode'] ?? 'encrypted');
    $passphrase = (string)($_POST['passphrase'] ?? '');
    $includeSecrets = !empty($_POST['include_secrets']);

    if ($mode === 'encrypted' && strlen($passphrase) < 8) {
        flash('danger', 'La phrase de passe doit contenir au moins 8 caract\u00e8res.');
        header('Location: /admin/backup');
        exit;
    }

    try {
        $payload = $backupExporter->buildPayload($config, $includeSecrets);
        $body = $mode === 'plain'
            ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : $backupExporter->encryptPayload($payload, $passphrase);
        if ($body === false || $body === '') {
            throw new \RuntimeException('Impossible de générer le fichier de backup.');
        }

        $auditLogger->log('admin', 'backup_export', $currentUser, [
            'target_name' => $mode === 'plain' ? 'Plain backup export' : 'Encrypted backup export',
            'details' => [
                'mode' => $mode,
                'include_secrets' => $includeSecrets,
                'metadata_counts' => $payload['metadata']['stats'] ?? [],
            ],
        ]);

        $suffix = $includeSecrets ? 'full' : 'metadata';
        $filename = 'ddsafe-backup-' . date('Ymd-His') . '-' . $suffix . ($mode === 'plain' ? '.json' : '.enc.json');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $body;
        exit;
    } catch (\Exception $e) {
        flash('danger', 'Export impossible : ' . $e->getMessage());
        header('Location: /admin/backup');
        exit;
    }
}

if ($path === '/admin/backup/verify' && $method === 'POST') {
    if (!isset($_FILES['backup_file']) || (int)($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('danger', 'Veuillez sélectionner un fichier backup valide.');
        header('Location: /admin/backup');
        exit;
    }

    $raw = (string)file_get_contents((string)$_FILES['backup_file']['tmp_name']);
    $passphrase = (string)($_POST['verify_passphrase'] ?? '');

    try {
        $payload = $backupExporter->decodeBackup($raw, $passphrase);
        $summary = $backupExporter->verifyPayload($payload);
        $stats = $summary['stats'] ?? [];

        flash(
            'success',
            'Backup valide. OTP: ' . (int)($stats['otp_codes'] ?? 0)
            . ' (avec secret: ' . (int)($stats['otp_codes_with_secret'] ?? 0)
            . ', sans secret: ' . (int)($stats['otp_codes_without_secret'] ?? 0) . ').'
        );

        $auditLogger->log('admin', 'backup_verify', $currentUser, [
            'target_name' => 'Backup verification',
            'details' => $summary,
        ]);
    } catch (\Exception $e) {
        flash('danger', 'Vérification impossible : ' . $e->getMessage());
    }

    header('Location: /admin/backup');
    exit;
}

if ($path === '/admin/backup/import' && $method === 'POST') {
    if (!isset($_FILES['backup_file']) || (int)($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('danger', 'Veuillez sélectionner un fichier backup valide.');
        header('Location: /admin/backup');
        exit;
    }

    $raw = (string)file_get_contents((string)$_FILES['backup_file']['tmp_name']);
    $passphrase = (string)($_POST['import_passphrase'] ?? '');
    $overwrite = !empty($_POST['overwrite']);

    try {
        $payload = $backupExporter->decodeBackup($raw, $passphrase);
        $result = $backupExporter->importPayload($payload, $overwrite);

        flash(
            'success',
            'Import terminé. Tenants créés: ' . (int)($result['created_tenants'] ?? 0)
            . ', groupes créés: ' . (int)($result['created_groups'] ?? 0)
            . ', OTP créés: ' . (int)($result['created_codes'] ?? 0)
            . ', OTP mis à jour: ' . (int)($result['updated_codes'] ?? 0)
            . ', OTP ignorés: ' . (int)($result['skipped_codes'] ?? 0) . '.'
        );

        $auditLogger->log('admin', 'backup_import', $currentUser, [
            'target_name' => 'Backup import',
            'details' => array_merge($result, ['overwrite' => $overwrite]),
        ]);
    } catch (\Exception $e) {
        flash('danger', 'Import impossible : ' . $e->getMessage());
    }

    header('Location: /admin/backup');
    exit;
}

// ── Télécharger un fichier de sauvegarde ─────────────────────────
if ($path === '/admin/backup/download' && $method === 'GET') {
    $filename = (string)($_GET['filename'] ?? '');
    if ($filename !== basename($filename)
        || !preg_match('/^ddsafe-(auto|backup)-[a-zA-Z0-9_.()-]+\.json$/', $filename)) {
        http_response_code(400);
        die('Nom de fichier invalide.');
    }

    $schedulerOutputDir = rtrim((string)(($config['backup_scheduler'] ?? [])['output_dir'] ?? '/backups'), '/');
    $dirReal = realpath($schedulerOutputDir);
    $fp      = realpath($schedulerOutputDir . '/' . $filename);
    if ($fp === false || $dirReal === false || !str_starts_with($fp, $dirReal . '/')) {
        http_response_code(404);
        die('Fichier introuvable.');
    }

    $size = (int)filesize($fp);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-store');
    readfile($fp);
    exit;
}

// ── Supprimer un fichier de sauvegarde ─────────────────────────
if ($path === '/admin/backup/delete' && $method === 'POST') {
    $filename = (string)($_POST['filename'] ?? '');
    if ($filename !== basename($filename)
        || !preg_match('/^ddsafe-(auto|backup)-[a-zA-Z0-9_.()-]+\.json$/', $filename)) {
        flash('danger', 'Nom de fichier invalide.');
        header('Location: /admin/backup');
        exit;
    }

    $schedulerOutputDir = rtrim((string)(($config['backup_scheduler'] ?? [])['output_dir'] ?? '/backups'), '/');
    $dirReal = realpath($schedulerOutputDir);
    $fp      = realpath($schedulerOutputDir . '/' . $filename);
    if ($fp === false || $dirReal === false || !str_starts_with($fp, $dirReal . '/')) {
        flash('danger', 'Fichier introuvable ou accès refusé.');
        header('Location: /admin/backup');
        exit;
    }

    @unlink($fp);
    $auditLogger->log('admin', 'backup_delete', $currentUser, [
        'target_name' => $filename,
    ]);
    flash('success', 'Sauvegarde supprimée.');
    header('Location: /admin/backup');
    exit;
}

// ── Rotation manuelle des sauvegardes ───────────────────────────
if ($path === '/admin/backup/rotate' && $method === 'POST') {
    $schedule = strtolower(trim((string)($_POST['schedule'] ?? '')));
    if (!in_array($schedule, ['daily', 'weekly', 'monthly'], true)) {
        flash('danger', 'Période invalide.');
        header('Location: /admin/backup');
        exit;
    }

    $runtimeSettingsRot  = new RuntimeSettings($pb);
    $defaultSchedulerRot = $config['backup_scheduler'] ?? [];
    $schedulerConfigRot  = array_merge($defaultSchedulerRot, $runtimeSettingsRot->getJson('backup_scheduler', []));
    $retentionKey        = 'retention_' . $schedule;
    $keep                = max(1, (int)($schedulerConfigRot[$retentionKey] ?? 14));

    $schedulerOutputDir = rtrim((string)($defaultSchedulerRot['output_dir'] ?? '/backups'), '/');
    if (!is_dir($schedulerOutputDir)) {
        flash('warning', 'Dossier de sauvegardes introuvable.');
        header('Location: /admin/backup');
        exit;
    }

    $safeSchedule = preg_quote($schedule, '/');
    $files = [];
    foreach (scandir($schedulerOutputDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
            continue;
        }
        $fp = $schedulerOutputDir . '/' . $entry;
        if (!is_file($fp) || !preg_match('/^ddsafe-auto-' . $safeSchedule . '-.*\.json$/', $entry)) {
            continue;
        }
        $files[$fp] = filemtime($fp) ?: 0;
    }
    arsort($files);
    $toDelete = array_slice(array_keys($files), $keep);
    $deleted  = 0;
    foreach ($toDelete as $fp) {
        if (@unlink($fp)) {
            $deleted++;
        }
    }

    $auditLogger->log('admin', 'backup_rotate', $currentUser, [
        'target_name' => 'Rotation ' . $schedule,
        'details'     => ['schedule' => $schedule, 'kept' => $keep, 'deleted' => $deleted],
    ]);
    flash('success', 'Rotation ' . $schedule . ' : ' . $deleted . ' fichier(s) supprimé(s), ' . $keep . ' conservé(s).');
    header('Location: /admin/backup');
    exit;
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
