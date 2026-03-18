<?php
/** @var array $authFailures */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-shield-exclamation me-2"></i>Derniers échecs d'authentification</h3>
    <span class="badge bg-secondary"><?= count($authFailures) ?> tentative(s)</span>
</div>

<?php if (empty($authFailures)): ?>
<div class="empty-state">
    <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
    <p>Aucun échec d'authentification récent.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Login</th>
                    <th>Type</th>
                    <th>Raison</th>
                    <th>IP</th>
                    <th>User-Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($authFailures as $row): ?>
                <tr>
                    <td><small class="text-muted"><?= htmlspecialchars(substr((string)($row['occurred_at'] ?? ''), 0, 19)) ?></small></td>
                    <td><?= htmlspecialchars((string)($row['identity'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-light"><?= htmlspecialchars((string)($row['login_type'] ?? '-')) ?></span></td>
                    <td><code><?= htmlspecialchars((string)($row['reason'] ?? '-')) ?></code></td>
                    <td><small class="text-muted"><?= htmlspecialchars((string)($row['ip'] ?? '-')) ?></small></td>
                    <td><small class="text-muted"><?= htmlspecialchars(substr((string)($row['user_agent'] ?? '-'), 0, 70)) ?></small></td>
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
