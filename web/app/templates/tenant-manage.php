<?php
/** @var array $tenant */
/** @var string $role */
/** @var array $members */
/** @var array $addableUsers */

use App\PermissionManager;

ob_start();

// Check permissions
$canManageMembers = !empty($role) && PermissionManager::can($role, 'manage_members');
$canManageRoles = !empty($role) && PermissionManager::can($role, 'manage_roles');
$canEditSettings = !empty($role) && PermissionManager::can($role, 'edit_settings');
$canDeleteTenant = ($role === 'owner') || (($tenant['created_by'] ?? '') === ($currentUser['id'] ?? ''));
$showActionsColumn = $canManageMembers || $canManageRoles;
$roleHierarchy = PermissionManager::getRoleHierarchy($role);

$manageableRoleOptions = [];
foreach (PermissionManager::getValidRoles() as $candidateRole) {
    if (PermissionManager::getRoleHierarchy($candidateRole) < $roleHierarchy) {
        $manageableRoleOptions[] = $candidateRole;
    }
}
?>

<div class="page-header">
    <h3><i class="bi bi-gear me-2"></i>Gérer : <?= htmlspecialchars($tenant['name'] ?? '') ?></h3>
    <a href="/tenants" class="btn btn-ghost btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<div class="row">
    <!-- Tenant info -->
    <div class="col-md-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations</h5>
            </div>
            <div class="card-body">
                <?php if ($canEditSettings): ?>
                    <form method="POST" action="/tenants/update">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= htmlspecialchars($tenant['name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"
                                      rows="2"><?= htmlspecialchars($tenant['description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Enregistrer
                        </button>
                    </form>
                    <?php if ($canDeleteTenant): ?>
                        <hr>
                        <form method="POST" action="/tenants/delete"
                              onsubmit="return confirm('Êtes-vous sûr ? Tous les codes OTP de ce tenant seront perdus.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1"></i>Supprimer ce tenant
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Nom :</strong> <?= htmlspecialchars($tenant['name'] ?? '') ?></p>
                    <p><strong>Description :</strong> <?= htmlspecialchars($tenant['description'] ?? '-') ?></p>
                    <p><strong>Votre rôle :</strong>
                        <span class="badge bg-primary"><?= htmlspecialchars(PermissionManager::getRoleDescription($role) ?? $role) ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Members -->
    <div class="col-md-7 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Membres</h5>
                <span class="badge bg-primary"><?= count($members) ?></span>
            </div>
            <div class="card-body">
                <!-- Add member form -->
                <?php if ($canManageMembers): ?>
                    <form method="POST" action="/tenants/members/add" class="mb-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($tenant['id']) ?>">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select name="user_id" class="form-select" required>
                                    <option value="">-- Selectionner un utilisateur existant --</option>
                                    <?php foreach ($addableUsers as $candidate): ?>
                                        <option value="<?= htmlspecialchars($candidate['id']) ?>">
                                            <?= htmlspecialchars(($candidate['name'] ?? 'Sans nom') . ' <' . ($candidate['email'] ?? '') . '>') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="role" class="form-select">
                                    <?php foreach ($manageableRoleOptions as $candidateRole): ?>
                                                     <option value="<?= htmlspecialchars($candidateRole) ?>" <?= $candidateRole === 'viewer' ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(PermissionManager::getRoleDescription($candidateRole)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success w-100" <?= empty($addableUsers) ? 'disabled' : '' ?>>
                                    <i class="bi bi-person-plus me-1"></i>Ajouter
                                </button>
                            </div>
                        </div>
                        <?php if (empty($addableUsers)): ?>
                            <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                                <i class="bi bi-info-circle me-1"></i>Tous les utilisateurs existants sont déjà membres de ce tenant.
                            </p>
                        <?php endif; ?>
                    </form>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-auto">
                            <a href="/tenants/members/import?id=<?= htmlspecialchars($tenant['id']) ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-csv me-1"></i>Importer CSV
                            </a>
                        </div>
                    </div>
                    <hr>
                <?php endif; ?>

                <!-- Members list -->
                <?php if (empty($members)): ?>
                    <p class="text-muted text-center">Aucun membre.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Rôle</th>
                                    <?php if ($showActionsColumn): ?><th>Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $m):
                                    $u = $m['expand']['user'] ?? [];
                                    $memberRole = (string)($m['role'] ?? 'viewer');
                                    $canEditThisMemberRole = $canManageRoles
                                        && PermissionManager::getRoleHierarchy($memberRole) < $roleHierarchy;
                                ?>
                                    <tr>
                                        <td>
                                            <div><?= htmlspecialchars($u['name'] ?? 'Sans nom') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($u['email'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($canEditThisMemberRole): ?>
                                                <form method="POST" action="/tenants/members/update" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="membership_id"
                                                           value="<?= htmlspecialchars($m['id']) ?>">
                                                    <input type="hidden" name="tenant_id"
                                                           value="<?= htmlspecialchars($tenant['id']) ?>">
                                                    <select name="role" class="form-select form-select-sm d-inline-block"
                                                            style="width: auto;" onchange="this.form.submit()">
                                                        <?php foreach ($manageableRoleOptions as $candidateRole): ?>
                                                            <option value="<?= htmlspecialchars($candidateRole) ?>" <?= ($m['role'] ?? '') === $candidateRole ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars(PermissionManager::getRoleDescription($candidateRole)) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-<?= match($m['role']) {
                                                    'owner' => 'danger', 
                                                    'admin' => 'warning', 
                                                    'member' => 'primary', 
                                                    default => 'secondary'
                                                } ?>">
                                                    <?= htmlspecialchars(PermissionManager::getRoleDescription($m['role'] ?? 'member')) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($showActionsColumn): ?>
                                            <td>
                                                <?php if ($canManageMembers && PermissionManager::getRoleHierarchy($memberRole) < $roleHierarchy): ?>
                                                    <form method="POST" action="/tenants/members/remove"
                                                          onsubmit="return confirm('Retirer ce membre ?')">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="membership_id"
                                                               value="<?= htmlspecialchars($m['id']) ?>">
                                                        <input type="hidden" name="tenant_id"
                                                               value="<?= htmlspecialchars($tenant['id']) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-person-x"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Roles legend -->
        <div class="card mt-3">
            <div class="card-body small">
                <h6><i class="bi bi-info-circle me-1"></i>Description des rôles</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Propriétaire</dt>
                    <dd class="col-sm-9">Mêmes droits que Administrateur. Rôle réservé et non modifiable.</dd>
                    
                    <dt class="col-sm-3">Administrateur</dt>
                    <dd class="col-sm-9">Gère les membres et leurs rôles, accès complet aux codes OTP</dd>
                    
                    <dt class="col-sm-3">Membre</dt>
                    <dd class="col-sm-9">Peut ajouter, modifier et supprimer des codes OTP</dd>
                    
                    <dt class="col-sm-3">Observateur</dt>
                    <dd class="col-sm-9">Lecture seule : consulte les codes sans pouvoir les modifier</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
