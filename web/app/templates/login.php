<?php
/** @var string $pageTitle */
/** @var bool $ldapEnabled */
/** @var bool $oidcEnabled */
/** @var string $oidcButtonLabel */
/** @var string $rememberedIdentity */
/** @var string $rememberedLoginType */
$flashes = getFlash();
$appName = $config['app_name'] ?? 'DDSafe';
$useLdapTab = $ldapEnabled && (($rememberedLoginType ?? 'local') === 'ldap');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= htmlspecialchars($appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <img src="/assets/favicon.svg" alt="<?= htmlspecialchars($appName) ?>" width="40" height="40">
        </div>
        <h2><?= htmlspecialchars($appName) ?></h2>
        <p class="login-subtitle">Connectez-vous pour continuer</p>

        <?php foreach ($flashes as $f): ?>
            <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show">
                <?= htmlspecialchars($f['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>

        <?php if ($ldapEnabled): ?>
        <ul class="nav nav-tabs-dark mb-3" role="tablist">
            <li class="nav-item">
                <button class="nav-link<?= !$useLdapTab ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-local" type="button">
                    <i class="bi bi-envelope me-1"></i>Compte local
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link<?= $useLdapTab ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-ldap" type="button">
                    <i class="bi bi-windows me-1"></i>Active Directory
                </button>
            </li>
        </ul>
        <?php endif; ?>

        <div class="tab-content">
            <!-- Local login -->
            <div class="tab-pane fade<?= (!$ldapEnabled || !$useLdapTab) ? ' show active' : '' ?>" id="tab-local">
                <div class="login-card">
                    <form method="POST" action="/login">
                        <?= csrfField() ?>
                        <input type="hidden" name="login_type" value="local">
                        <div class="mb-3">
                            <label for="local-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="local-email" name="identity"
                                required <?= (!$ldapEnabled || !$useLdapTab) ? 'autofocus' : '' ?> placeholder="votre@email.com"
                                   value="<?= htmlspecialchars($rememberedIdentity ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="local-password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="local-password" name="password"
                                   required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember-identity-local" name="remember_identity" value="1"
                                   <?= !empty($rememberedIdentity) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remember-identity-local">
                                Enregistrer mon login
                            </label>
                        </div>
                        <button type="submit" class="btn btn-accent w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($ldapEnabled): ?>
            <!-- LDAP login -->
            <div class="tab-pane fade<?= $useLdapTab ? ' show active' : '' ?>" id="tab-ldap">
                <div class="login-card">
                    <form method="POST" action="/login">
                        <?= csrfField() ?>
                        <input type="hidden" name="login_type" value="ldap">
                        <div class="mb-3">
                            <label for="ldap-user" class="form-label">Nom d'utilisateur AD</label>
                            <input type="text" class="form-control" id="ldap-user" name="identity"
                                required <?= $useLdapTab ? 'autofocus' : '' ?> placeholder="prenom.nom"
                                   value="<?= htmlspecialchars($rememberedIdentity ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="ldap-password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="ldap-password" name="password"
                                   required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember-identity-ldap" name="remember_identity" value="1"
                                   <?= !empty($rememberedIdentity) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remember-identity-ldap">
                                Enregistrer mon login
                            </label>
                        </div>
                        <button type="submit" class="btn btn-accent w-100">
                            <i class="bi bi-windows me-1"></i>Se connecter via AD
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($oidcEnabled): ?>
        <div class="mt-3">
            <div class="d-flex align-items-center mb-3">
                <hr class="flex-grow-1">
                <span class="mx-2 text-muted small">ou</span>
                <hr class="flex-grow-1">
            </div>
            <a href="/auth/oidc" class="btn btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i><?= htmlspecialchars($oidcButtonLabel) ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
