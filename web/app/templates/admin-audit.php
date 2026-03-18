<?php
/** @var array $auditItems */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-journal-text me-2"></i>Journal d'audit</h3>
    <span class="badge bg-secondary"><?= count($auditItems) ?> évènement(s)</span>
</div>

<?php if (empty($auditItems)): ?>
<div class="empty-state">
    <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
    <p>Aucun évènement d'audit disponible.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Catégorie</th>
                    <th>Action</th>
                    <th>Acteur</th>
                    <th>Cible</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditItems as $item): ?>
                <tr>
                    <td><small class="text-muted"><?= htmlspecialchars(substr((string)($item['logged_at'] ?? ''), 0, 19)) ?></small></td>
                    <td><span class="badge text-bg-light"><?= htmlspecialchars((string)($item['category'] ?? '-')) ?></span></td>
                    <td><code><?= htmlspecialchars((string)($item['action'] ?? '-')) ?></code></td>
                    <td><?= htmlspecialchars((string)($item['actor_name'] ?? '-')) ?></td>
                    <td>
                        <div><?= htmlspecialchars((string)($item['target_name'] ?? '-')) ?></div>
                        <?php if (!empty($item['target_id'])): ?>
                        <small class="text-muted">ID: <?= htmlspecialchars((string)$item['target_id']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars((string)($item['ip'] ?? '-')) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
