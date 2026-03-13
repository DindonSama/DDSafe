<?php
/** @var array $trashedCodes */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-trash3 me-2"></i>Corbeille</h3>
    <?php if (!empty($trashedCodes)): ?>
    <form method="POST" action="/admin/trash/empty" onsubmit="return confirm('Vider définitivement la corbeille ?')">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-danger btn-sm">
            <i class="bi bi-trash3-fill me-1"></i>Vider la corbeille
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($trashedCodes)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-trash3"></i></div>
        <p>La corbeille est vide.</p>
    </div>
<?php else: ?>
    <div class="card">
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

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
