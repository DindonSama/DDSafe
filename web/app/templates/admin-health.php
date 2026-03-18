<?php
/** @var array $pocketbaseStatus */
/** @var int $pocketbaseLatencyMs */
/** @var int $latencyAvgMs */
/** @var bool $ldapEnabled */
/** @var bool $ldapConfigured */
/** @var bool|null $ldapReachable */
/** @var bool $oidcEnabled */
/** @var bool $oidcConfigured */
/** @var int $otpCount */
/** @var int $backupCount */
/** @var array $backupDisk */
/** @var array $schedulerConfigHealth */
/** @var array $schedulerStateHealth */
/** @var array $nextRunBySchedule */
/** @var array $authFailureWindow */
/** @var array $sessionHealth */
/** @var array $backupIntegrity */
/** @var array $healthEvents */
/** @var array $authFailures */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-heart-pulse-fill me-2"></i>Santé applicative</h3>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">PocketBase</div>
                <div class="fw-semibold <?= !empty($pocketbaseStatus['ok']) ? 'text-success' : 'text-danger' ?>">
                    <?= htmlspecialchars((string)($pocketbaseStatus['message'] ?? 'Inconnu')) ?>
                </div>
                <small class="text-muted">Latence: <?= (int)$pocketbaseLatencyMs ?> ms (moy. 10 checks: <?= (int)$latencyAvgMs ?> ms)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">LDAP</div>
                <div class="fw-semibold">
                    <?= $ldapEnabled ? 'Activé' : 'Désactivé' ?>
                </div>
                <small class="text-muted">Config: <?= $ldapConfigured ? 'OK' : 'Incomplète' ?></small>
                <?php if ($ldapReachable !== null): ?>
                    <div class="small <?= $ldapReachable ? 'text-success' : 'text-danger' ?>">
                        Connectivité: <?= $ldapReachable ? 'OK' : 'Échec' ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">OIDC</div>
                <div class="fw-semibold">
                    <?= $oidcEnabled ? 'Activé' : 'Désactivé' ?>
                </div>
                <small class="text-muted">Config: <?= $oidcConfigured ? 'OK' : 'Incomplète' ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Nombre de codes OTP</div>
                <div class="fw-semibold"><?= (int)$otpCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Sauvegardes stockées</div>
                <div class="fw-semibold"><?= (int)$backupCount ?></div>
                <small><a href="/admin/backup" class="text-muted">Gérer →</a></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 <?= !empty($backupDisk['alert']) ? 'border-warning' : '' ?>">
            <div class="card-body">
                <div class="text-muted small mb-1">Espace backup disponible</div>
                <div class="fw-semibold"><?= (int)($backupDisk['free_pct'] ?? 0) ?>%</div>
                <small class="text-muted">
                    <?= number_format(((int)($backupDisk['free'] ?? 0)) / (1024 * 1024 * 1024), 2) ?> Go libres /
                    <?= number_format(((int)($backupDisk['total'] ?? 0)) / (1024 * 1024 * 1024), 2) ?> Go
                </small>
                <?php if (!empty($backupDisk['alert'])): ?>
                    <div class="small text-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Seuil bas (&lt; 15%)</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Intégrité dernier backup</div>
                <?php
                $integrityClass = 'text-muted';
                if (($backupIntegrity['status'] ?? '') === 'ok') {
                    $integrityClass = 'text-success';
                } elseif (($backupIntegrity['status'] ?? '') === 'warning') {
                    $integrityClass = 'text-warning';
                } elseif (($backupIntegrity['status'] ?? '') === 'error') {
                    $integrityClass = 'text-danger';
                }
                ?>
                <div class="fw-semibold <?= $integrityClass ?>"><?= htmlspecialchars((string)($backupIntegrity['status'] ?? 'unknown')) ?></div>
                <small class="text-muted d-block"><?= htmlspecialchars((string)($backupIntegrity['file'] ?? '-')) ?></small>
                <small class="text-muted d-block"><?= htmlspecialchars((string)($backupIntegrity['message'] ?? '')) ?></small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><strong>Échecs d'auth (fenêtres glissantes)</strong></div>
            <div class="card-body d-flex justify-content-between">
                <div><div class="text-muted small">5 min</div><div class="fw-semibold"><?= (int)($authFailureWindow['5m'] ?? 0) ?></div></div>
                <div><div class="text-muted small">1 h</div><div class="fw-semibold"><?= (int)($authFailureWindow['1h'] ?? 0) ?></div></div>
                <div><div class="text-muted small">24 h</div><div class="fw-semibold"><?= (int)($authFailureWindow['24h'] ?? 0) ?></div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><strong>Santé sessions</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span>Sessions actives estimées</span><strong><?= (int)($sessionHealth['active'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Fichiers session</span><strong><?= (int)($sessionHealth['files_total'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between"><span>Expirations (24 h)</span><strong><?= (int)($sessionHealth['expired_24h'] ?? 0) ?></strong></div>
                <small class="text-muted">Timeout configuré: <?= (int)($sessionHealth['timeout_seconds'] ?? 0) ?>s</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><strong>Jobs scheduler</strong></div>
            <div class="card-body">
                <?php
                $schedulerEnabledGlobal = !empty($schedulerConfigHealth['enabled']);
                $enabledSchedulesHealth = array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string)($schedulerConfigHealth['schedules'] ?? 'daily,weekly,monthly'))
                )));
                ?>
                <?php foreach (['daily', 'weekly', 'monthly'] as $sch): ?>
                    <?php
                    $state = $schedulerStateHealth[$sch] ?? [];
                    if (!$schedulerEnabledGlobal || !in_array($sch, $enabledSchedulesHealth, true)) {
                        $status = 'disabled';
                    } elseif (!empty($state['last_status'])) {
                        $status = (string)$state['last_status'];
                    } elseif (!empty($state['last_run_at'])) {
                        // Compat: old state file may not include last_status.
                        $status = 'success';
                    } else {
                        $status = 'pending';
                    }

                    $statusClass = 'text-muted';
                    if ($status === 'success') {
                        $statusClass = 'text-success';
                    } elseif ($status === 'error') {
                        $statusClass = 'text-danger';
                    } elseif ($status === 'pending') {
                        $statusClass = 'text-warning';
                    }
                    ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars(ucfirst($sch)) ?></strong>
                            <span class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                        </div>
                        <small class="text-muted d-block">Dernier run: <?= htmlspecialchars(substr((string)($state['last_run_at'] ?? '-'), 0, 19)) ?></small>
                        <small class="text-muted d-block">Prochain run: <?= htmlspecialchars(substr((string)($nextRunBySchedule[$sch] ?? '-'), 0, 19)) ?></small>
                    </div>
                <?php endforeach; ?>
                <a href="/admin/backup" class="small">Configurer le scheduler →</a>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <strong>Événements santé récents</strong>
    </div>
    <div class="card-body table-responsive">
        <?php if (empty($healthEvents)): ?>
            <p class="text-muted mb-0">Aucun événement récent.</p>
        <?php else: ?>
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Niveau</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($healthEvents as $event): ?>
                        <?php
                        $level = (string)($event['level'] ?? 'info');
                        $badge = $level === 'danger' ? 'text-bg-danger' : ($level === 'warning' ? 'text-bg-warning' : 'text-bg-light');
                        ?>
                        <tr>
                            <td><small class="text-muted"><?= htmlspecialchars(substr((string)($event['at'] ?? ''), 0, 19)) ?></small></td>
                            <td><?= htmlspecialchars((string)($event['source'] ?? '-')) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($level) ?></span></td>
                            <td><?= htmlspecialchars((string)($event['message'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
