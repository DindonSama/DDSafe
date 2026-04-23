<?php
/** @var string $pageTitle */
/** @var string $content */
/** @var array $config */
/** @var array|null $currentUser */
$flashes = getFlash();
$appName = $config['app_name'] ?? 'DDSafe';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '') ?> — <?= htmlspecialchars($appName) ?></title>
    <script>document.documentElement.classList.add('js');</script>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <meta name="csrf-token" content="<?= csrfToken() ?>">
</head>
<body>
<a href="#main-content" class="skip-link">Aller au contenu principal</a>
<?php if (!empty($currentUser)): ?>

<!-- Mobile sidebar toggle -->
<button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Ouvrir ou fermer le menu" aria-controls="sidebar" aria-expanded="false">
    <i class="bi bi-list" aria-hidden="true"></i>
</button>

<!-- Sidebar -->
<aside class="app-sidebar" id="sidebar" aria-label="Navigation latérale">
    <a href="/" class="sidebar-brand" aria-label="Aller au tableau de bord">
        <div class="brand-icon">
            <img src="/assets/favicon.svg" alt="<?= htmlspecialchars($appName) ?>" width="22" height="22">
        </div>
        <span class="brand-text"><?= htmlspecialchars($appName) ?></span>
    </a>

    <nav class="sidebar-nav" aria-label="Navigation principale">
        <div class="nav-section">Menu</div>
        <a href="/otp" class="nav-link <?= str_starts_with($currentPath, '/otp') && $currentPath !== '/otp/import' ? 'active' : '' ?>">
            <i class="bi bi-key-fill"></i> Codes OTP
        </a>
        <a href="/otp/import" class="nav-link <?= $currentPath === '/otp/import' ? 'active' : '' ?>">
            <i class="bi bi-qr-code-scan"></i> Importer
        </a>

        <?php if (!empty($currentUser['can_access_tenants_menu'])): ?>
        <div class="nav-section mt-3">Organisation</div>
        <a href="/tenants" class="nav-link <?= str_starts_with($currentPath, '/tenants') ? 'active' : '' ?>">
            <i class="bi bi-building"></i> Collections
        </a>
        <?php endif; ?>

        <?php if (!empty($currentUser['is_app_admin'])): ?>
        <div class="nav-section mt-3">Administration</div>
        <a href="/admin/users" class="nav-link <?= $currentPath === '/admin/users' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Utilisateurs
        </a>
        <a href="/admin/trash" class="nav-link <?= $currentPath === '/admin/trash' ? 'active' : '' ?>">
            <i class="bi bi-trash3"></i> Corbeille
        </a>
        <a href="/admin/health" class="nav-link <?= $currentPath === '/admin/health' ? 'active' : '' ?>">
            <i class="bi bi-heart-pulse"></i> Santé
        </a>
        <a href="/admin/backup" class="nav-link <?= $currentPath === '/admin/backup' ? 'active' : '' ?>">
            <i class="bi bi-cloud-arrow-down"></i> Sauvegardes
        </a>
        <a href="/admin/audit" class="nav-link <?= $currentPath === '/admin/audit' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Journal d'audit
        </a>
        <a href="/admin/auth-failures" class="nav-link <?= $currentPath === '/admin/auth-failures' ? 'active' : '' ?>">
            <i class="bi bi-shield-exclamation"></i> Echecs d'auth
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?= strtoupper(mb_substr($currentUser['name'] ?: $currentUser['email'], 0, 1)) ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($currentUser['name'] ?: $currentUser['email']) ?></div>
            <div class="user-role">
                <?php if (!empty($currentUser['is_app_admin'])): ?>
                    Administrateur
                <?php elseif (!empty($currentUser['can_manage_any_tenant'])): ?>
                    Manager
                <?php else: ?>
                    Utilisateur
                <?php endif; ?>
            </div>
        </div>
        <a href="/settings" class="btn-logout" title="Paramètres" aria-label="Ouvrir les paramètres">
            <i class="bi bi-gear-fill" aria-hidden="true"></i>
        </a>
        <a href="/logout" class="btn-logout" title="Déconnexion" aria-label="Se déconnecter">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</aside>

<main class="app-main" id="main-content" tabindex="-1">
    <div class="visually-hidden" aria-live="polite" id="a11y-live"></div>
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert" aria-live="assertive">
            <?= htmlspecialchars($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer l'alerte"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<?php else: ?>

<main id="main-content" tabindex="-1">
    <div class="visually-hidden" aria-live="polite" id="a11y-live"></div>
    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show mx-3 mt-3" role="alert" aria-live="assertive">
            <?= htmlspecialchars($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer l'alerte"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
