<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// ── Session ──────────────────────────────────────────────────────
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

// ── Config & Services ────────────────────────────────────────────
$config        = require __DIR__ . '/../config/config.php';
$pb            = new \App\PocketBaseClient($config['pocketbase']['url']);
$auth          = new \App\Auth($pb, $config);
$otpManager    = new \App\OTPManager($pb, $config['app_secret']);
$tenantManager = new \App\TenantManager($pb);
$maxLogEntries = max(50, (int)($config['log_max_entries'] ?? 500));
$logRetentionDays = max(1, (int)($config['log_retention_days'] ?? 30));
$auditLogger   = new \App\AuditLogger($pb, $maxLogEntries, $logRetentionDays);
$securityLogger = new \App\SecurityLogger($pb, $maxLogEntries, $logRetentionDays);

// ── Auto-setup on first run ──────────────────────────────────────
$setup = new \App\Setup($pb, $config);
if (!$setup->isInitialized()) {
    try {
        $setup->initialize();
    } catch (\Exception $e) {
        error_log('Setup failed: ' . $e->getMessage());
    }
}

// Ensure admin token for all PocketBase operations
if ($auth->isAuthenticated()) {
    try {
        $auth->ensureAdminToken();
    } catch (\Exception) {
        // Token expired — clear it
        unset($_SESSION['pb_admin_token']);
        try { $auth->ensureAdminToken(); } catch (\Exception) {}
    }
}

// ── Flash messages ───────────────────────────────────────────────
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlash(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

// ── CSRF ─────────────────────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function verifyCsrf(): bool
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrfToken(), $token);
}

// ── Routing ──────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path   = rtrim($path, '/') ?: '/';

$publicRoutes = ['/login', '/auth/oidc', '/auth/oidc/callback'];
$isPublic     = in_array($path, $publicRoutes, true);

// Auto logout after 15 minutes of inactivity.
if (!$isPublic && $auth->isAuthenticated()) {
    $idleTimeout = max(60, (int)($config['session_timeout_seconds'] ?? 900));
    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);

    if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeout) {
        $securityLogger->logAuthFailure((string)($currentUser['email'] ?? ''), 'session', 'session_timeout');
        $auth->logout();
        session_regenerate_id(true);
        $_SESSION['flash'][] = [
            'type' => 'warning',
            'message' => 'Session expiree apres 15 minutes d\'inactivite. Veuillez vous reconnecter.',
        ];
        header('Location: /login');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

if (!$isPublic && !$auth->isAuthenticated()) {
    header('Location: /login');
    exit;
}

// CSRF check for POST (except API routes)
if ($method === 'POST' && !str_starts_with($path, '/api/')) {
    if (!verifyCsrf()) {
        http_response_code(403);
        die('Jeton CSRF invalide. Veuillez rafraîchir la page et réessayer.');
    }
}

// Current user shortcut
$currentUser = $auth->getCurrentUser();

if (!empty($currentUser)) {
    // Keep user flags in sync with PocketBase so admin changes apply immediately.
    $currentUserId = (string)($currentUser['id'] ?? '');
    if ($currentUserId !== '') {
        try {
            $freshUser = $pb->getRecord('users', $currentUserId);
            if (!empty($freshUser)) {
                $currentUser['email'] = (string)($freshUser['email'] ?? ($currentUser['email'] ?? ''));
                $currentUser['name'] = (string)($freshUser['name'] ?? ($currentUser['name'] ?? ''));
                $currentUser['is_app_admin'] = !empty($freshUser['is_app_admin']);
                $currentUser['allow_personal_otp'] = !empty($freshUser['allow_personal_otp']);
                $currentUser['is_ad_user'] = !empty($freshUser['is_ad_user']);
                $currentUser['is_oidc_user'] = !empty($freshUser['is_oidc_user']);
            }
        } catch (\Exception) {
            // Ignore sync failures and continue with session user snapshot.
        }
    }

    $userTenants = $tenantManager->getUserTenants((string)$currentUser['id']);
    $canManageAnyTenant = false;

    foreach ($userTenants as $tenant) {
        if ($auth->canDoInTenant((string)($tenant['id'] ?? ''), 'manage_members')) {
            $canManageAnyTenant = true;
            break;
        }
    }

    $currentUser['can_manage_any_tenant'] = $canManageAnyTenant;
    $currentUser['can_access_tenants_menu'] = !empty($currentUser['is_app_admin']) || $canManageAnyTenant;
    $_SESSION['user'] = $currentUser;
}


try {
    if ($path === '/login') {
        require __DIR__ . '/../routes/auth.php';
    } elseif ($path === '/logout') {
        require __DIR__ . '/../routes/logout.php';
    } elseif ($path === '/' || $path === '/dashboard') {
        header('Location: /otp');
        exit;
    } elseif (str_starts_with($path, '/otp')) {
        require __DIR__ . '/../routes/otp.php';
    } elseif (str_starts_with($path, '/settings')) {
        require __DIR__ . '/../routes/settings.php';
    } elseif (str_starts_with($path, '/tenants')) {
        require __DIR__ . '/../routes/tenants.php';
    } elseif (str_starts_with($path, '/auth/oidc')) {
        require __DIR__ . '/../routes/oidc.php';
    } elseif (str_starts_with($path, '/admin')) {
        require __DIR__ . '/../routes/admin.php';
    } elseif (str_starts_with($path, '/extension')) {
        require __DIR__ . '/../routes/extension.php';
    } elseif (str_starts_with($path, '/api/')) {
        require __DIR__ . '/../routes/api.php';
    } else {
        http_response_code(404);
        $pageTitle = 'Page introuvable';
        require __DIR__ . '/../templates/404.php';
    }
} catch (\Exception $e) {
    error_log('Application error: ' . $e->getMessage());
    http_response_code(500);
    echo '<h1>Erreur</h1><p>Une erreur est survenue. Veuillez réessayer.</p>';
}
