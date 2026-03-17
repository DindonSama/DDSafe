<?php
/** @var array $trashedCodes */
/** @var array $trashedTenants */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-trash3 me-2"></i>Corbeille</h3>
    <?php if (!empty($trashedCodes) || !empty($trashedTenants)): ?>
    <form method="POST" action="/admin/trash/empty" onsubmit="return confirm('Vider définitivement la corbeille ?')">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash3-fill me-1"></i>Vider la corbeille
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($trashedCodes) && empty($trashedTenants)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-trash3"></i></div>
        <p>La corbeille est vide.</p>
    </div>
<?php endif; ?>

<?php if (!empty($trashedCodes)): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Codes OTP supprimés</strong>
            <span class="badge bg-secondary"><?= count($trashedCodes) ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Émetteur</th>
                            <th>Supprimé par</th>
                            <th>Date de suppression</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashedCodes as $code):
                            $deletedBy = $code['expand']['deleted_by'] ?? null;
                            $deletedByName = $deletedBy
                                ? ($deletedBy['name'] ?: $deletedBy['email'] ?? '?')
                                : ($code['deleted_by'] ?: '?');
                            $deletedAt = $code['deleted_at'] ?? '';
                            if ($deletedAt) {
                                try {
                                    $dt = new \DateTime($deletedAt);
                                    $deletedAtFormatted = $dt->format('d/m/Y H:i');
                                } catch (\Exception) {
                                    $deletedAtFormatted = htmlspecialchars($deletedAt);
                                }
                            } else {
                                $deletedAtFormatted = '-';
                            }
                            $tenant = $code['expand']['tenant'] ?? null;
                            $tenantName = $tenant ? $tenant['name'] : '';
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($code['name'] ?? '') ?></strong>
                                    <?php if ($tenantName): ?>
                                        <br><small class="text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars($tenantName) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($code['issuer'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($deletedByName) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= $deletedAtFormatted ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form method="POST" action="/admin/trash/restore" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($code['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/admin/trash/delete" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement ce code ?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($code['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer définitivement">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($trashedTenants)): ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Collections supprimées</strong>
            <span class="badge bg-secondary"><?= count($trashedTenants) ?></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Supprimé par</th>
                            <th>Date de suppression</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashedTenants as $tenant):
                            $deletedBy = $tenant['expand']['deleted_by'] ?? null;
                            $deletedByName = $deletedBy
                                ? ($deletedBy['name'] ?: $deletedBy['email'] ?? '?')
                                : ($tenant['deleted_by'] ?: '?');
                            $deletedAt = $tenant['deleted_at'] ?? '';
                            if ($deletedAt) {
                                try {
                                    $dt = new \DateTime($deletedAt);
                                    $deletedAtFormatted = $dt->format('d/m/Y H:i');
                                } catch (\Exception) {
                                    $deletedAtFormatted = htmlspecialchars($deletedAt);
                                }
                            } else {
                                $deletedAtFormatted = '-';
                            }
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tenant['name'] ?? '') ?></strong></td>
                                <td><?= htmlspecialchars($tenant['description'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($deletedByName) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= $deletedAtFormatted ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <form method="POST" action="/admin/trash/tenant/restore" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Restaurer">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="/admin/trash/tenant/delete" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement cette collection ?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($tenant['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer définitivement">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
