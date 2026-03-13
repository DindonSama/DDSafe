<?php
/** @var array $allUsers */
/** @var array $allTenants */
/** @var array $membershipsByUser */
/** @var string $openTenantUserId */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs</h3>
    <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="bi bi-person-plus me-1"></i>Créer un utilisateur
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Rôle</th>
                        <th>Tenants</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <?php
                        $userMemberships = $membershipsByUser[$u['id']] ?? [];
                        $roleCounts = ['owner' => 0, 'admin' => 0, 'member' => 0, 'viewer' => 0];
                        foreach ($userMemberships as $m) {
                            $roleKey = (string)($m['role'] ?? 'viewer');
                            if (isset($roleCounts[$roleKey])) {
                                $roleCounts[$roleKey]++;
                            }
                        }
                        $totalTenants = count($userMemberships);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($u['is_ad_user'])): ?>
                                    <span class="badge bg-info"><i class="bi bi-windows me-1"></i>AD</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Local</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="/admin/users/toggle-admin" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                        <input type="hidden" name="is_app_admin"
                                               value="<?= empty($u['is_app_admin']) ? '1' : '0' ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($u['is_app_admin']) ? 'btn-warning' : 'btn-outline-secondary' ?>">
                                            <?= !empty($u['is_app_admin']) ? '<i class="bi bi-star-fill"></i> Admin' : '<i class="bi bi-star"></i>' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <?php if ($totalTenants === 0): ?>
                                    <span class="text-muted small">Aucun</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge bg-secondary">Total: <?= $totalTenants ?></span>
                                        <span class="badge bg-danger">Owner: <?= (int)$roleCounts['owner'] ?></span>
                                        <span class="badge bg-warning text-dark">Admin: <?= (int)$roleCounts['admin'] ?></span>
                                        <span class="badge bg-primary">Membre: <?= (int)$roleCounts['member'] ?></span>
                                        <span class="badge bg-secondary">Observateur: <?= (int)$roleCounts['viewer'] ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= htmlspecialchars(substr($u['created'] ?? '', 0, 10)) ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-info"
                                            data-bs-toggle="modal"
                                            data-bs-target="#manageTenantsModal-<?= htmlspecialchars($u['id']) ?>"
                                            title="Gérer les tenants">
                                        <i class="bi bi-building-gear"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-user"
                                            data-id="<?= htmlspecialchars($u['id']) ?>"
                                            data-name="<?= htmlspecialchars($u['name'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
                                            title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (($u['id'] ?? '') !== ($currentUser['id'] ?? '')): ?>
                                        <form method="POST" action="/admin/users/delete" class="d-inline"
                                              onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-secondary align-self-center">Vous</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($allUsers as $u): ?>
    <?php
    $uid = (string)($u['id'] ?? '');
    $userMemberships = $membershipsByUser[$uid] ?? [];
    $existingTenantIds = [];
    foreach ($userMemberships as $m) {
        $existingTenantIds[(string)($m['tenant'] ?? '')] = true;
    }
    ?>
    <div class="modal fade" id="manageTenantsModal-<?= htmlspecialchars($uid) ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-building-gear me-2"></i>Tenants de <?= htmlspecialchars($u['name'] ?? $u['email'] ?? 'Utilisateur') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="/admin/users/tenants/add" class="mb-3">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($uid) ?>">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select name="tenant_id" class="form-select" required>
                                    <option value="">-- Sélectionner un tenant --</option>
                                    <?php foreach ($allTenants as $t):
                                        $tid = (string)($t['id'] ?? '');
                                        if ($tid === '' || isset($existingTenantIds[$tid])) {
                                            continue;
                                        }
                                    ?>
                                        <option value="<?= htmlspecialchars($tid) ?>"><?= htmlspecialchars($t['name'] ?? 'Tenant') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="role" class="form-select">
                                    <option value="admin">Administrateur</option>
                                    <option value="member">Membre</option>
                                    <option value="viewer" selected>Observateur</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-plus-circle me-1"></i>Ajouter
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($userMemberships)): ?>
                        <p class="text-muted mb-0">Aucun tenant associé.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Rôle</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userMemberships as $m):
                                        $tenantName = $m['expand']['tenant']['name'] ?? 'Tenant';
                                        $mid = (string)($m['id'] ?? '');
                                        $mrole = (string)($m['role'] ?? 'viewer');
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tenantName) ?></td>
                                            <td>
                                                <?php if ($mrole === 'owner'): ?>
                                                    <span class="badge bg-danger">owner</span>
                                                <?php else: ?>
                                                    <form method="POST" action="/admin/users/tenants/update" class="d-inline">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="membership_id" value="<?= htmlspecialchars($mid) ?>">
                                                        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <option value="admin" <?= $mrole === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                                            <option value="member" <?= $mrole === 'member' ? 'selected' : '' ?>>Membre</option>
                                                            <option value="viewer" <?= $mrole === 'viewer' ? 'selected' : '' ?>>Observateur</option>
                                                        </select>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($mrole === 'owner'): ?>
                                                    <span class="text-muted small">Verrouillé</span>
                                                <?php else: ?>
                                                    <form method="POST" action="/admin/users/tenants/remove" class="d-inline" onsubmit="return confirm('Retirer cet utilisateur de ce tenant ?')">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="membership_id" value="<?= htmlspecialchars($mid) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-person-x"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!empty($openTenantUserId)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('manageTenantsModal-<?= htmlspecialchars($openTenantUserId) ?>');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    </script>
<?php endif; ?>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/users/create">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nouvel utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_app_admin" value="1">
                        <label class="form-check-label">Administrateur</label>
                    </div>
                    <div class="form-check mt-2">
                        <small class="text-muted">Role global: Administrateur ou Utilisateur.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/admin/users/edit">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" id="edit-user-id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier l'utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" id="edit-user-email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" id="edit-user-name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="8"
                               placeholder="Laisser vide pour ne pas changer">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
