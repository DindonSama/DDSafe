<?php
/** @var string $pageTitle */
/** @var string $extensionDownloadUrl */
/** @var string $extensionPageUrl */
/** @var string $extensionAppUrl */
/** @var bool $isLocalUser */
/** @var array $currentUser */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-gear-fill me-2"></i>Paramètres personnels</h3>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Extension navigateur</strong></div>
    <div class="card-body">
        <p class="mb-2">Téléchargez l'extension Chrome/Edge (lecture seule) :</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-ghost" href="<?= htmlspecialchars($extensionPageUrl) ?>">
                <i class="bi bi-puzzle me-1"></i>Page extension
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Sécurité du compte</strong></div>
    <div class="card-body">
        <div class="mb-3 small" style="color:var(--text-muted)">
            Compte : <?= htmlspecialchars($currentUser['email'] ?? '') ?>
        </div>

        <?php if ($isLocalUser): ?>
            <form method="POST" action="/settings/password" class="row g-3" autocomplete="off">
                <?= csrfField() ?>
                <div class="col-12">
                    <label class="form-label">Mot de passe actuel</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-accent">
                        <i class="bi bi-shield-lock me-1"></i>Changer mon mot de passe
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                Ce compte est géré via LDAP/OIDC. Le mot de passe doit être changé dans votre fournisseur d'identité.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
