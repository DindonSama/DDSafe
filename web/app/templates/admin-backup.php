<?php
/** @var array $backupItems */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-cloud-arrow-down-fill me-2"></i>Sauvegardes PocketBase</h3>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-archive me-1"></i>Sauvegardes disponibles (<?= count($backupItems) ?>)</strong>
        <a href="/admin/backup" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i>Rafraichir
        </a>
    </div>

    <?php if (empty($backupItems)): ?>
        <div class="card-body text-muted">
            <i class="bi bi-inbox me-1"></i>Aucune sauvegarde PocketBase trouvee.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Taille (Ko)</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupItems as $item): ?>
                        <?php
                        $name = (string)($item['name'] ?? $item['key'] ?? $item['filename'] ?? '-');
                        $size = (int)($item['size'] ?? 0);
                        $created = (string)($item['created'] ?? $item['modified'] ?? $item['updated'] ?? '');
                        $createdFmt = '-';
                        if ($created !== '') {
                            $ts = strtotime($created);
                            if ($ts !== false) {
                                $createdFmt = date('d/m/Y H:i', $ts);
                            }
                        }
                        $sizeKb = (int)max(1, ceil($size / 1024));
                        ?>
                        <tr>
                            <td><code class="small"><?= htmlspecialchars($name) ?></code></td>
                            <td><small class="text-muted"><?= $sizeKb ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($createdFmt) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
