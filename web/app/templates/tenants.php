<?php
/** @var array $userTenants */
/** @var string $pageTitle */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-building me-2"></i><?= htmlspecialchars($pageTitle) ?></h3>
    <?php if (!empty($canCreateTenant)): ?>
        <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#createTenantModal">
            <i class="bi bi-plus-lg me-1"></i>Créer un tenant
        </button>
    <?php endif; ?>
</div>

<?php if (empty($userTenants)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Vous n'êtes membre d'aucun tenant. Créez-en un pour commencer à partager des codes OTP.
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($userTenants as $t): ?>
            <div class="col-md-4 col-sm-6">
                <div class="tenant-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0" style="font-size:.95rem;font-weight:600"><?= htmlspecialchars($t['name']) ?></h5>
                            <span class="badge bg-<?= match($t['_role'] ?? '') {
                                'admin' => 'danger',
                                'member' => 'primary',
                                'viewer' => 'secondary',
                                default => 'secondary',
                            } ?>"><?= htmlspecialchars($t['_role'] ?? 'member') ?></span>
                        </div>
                        <?php if (!empty($t['description'])): ?>
                            <p class="small" style="color:var(--text-muted)"><?= htmlspecialchars($t['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <form method="POST" action="/tenants/select" class="flex-grow-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars($t['id']) ?>">
                            <input type="hidden" name="redirect" value="/otp">
                            <button type="submit" class="btn btn-sm btn-ghost w-100">
                                <i class="bi bi-eye me-1"></i>Voir les codes
                            </button>
                        </form>
                        <?php if (in_array(($t['_role'] ?? ''), ['owner', 'admin'], true)): ?>
                            <a href="/tenants/manage?id=<?= htmlspecialchars($t['id']) ?>"
                               class="btn btn-sm btn-ghost"
                               title="Modifier ce tenant"
                               aria-label="Modifier ce tenant">
                                <i class="bi bi-gear me-1"></i>Modifier
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($canCreateTenant)): ?>
<!-- Create Tenant Modal -->
<div class="modal fade" id="createTenantModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/tenants/create">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Nouveau tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="Ex: Équipe DevOps">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Description optionnelle"></textarea>
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
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
