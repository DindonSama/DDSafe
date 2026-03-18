<?php
/** @var array $backupFiles */
/** @var array $schedulerConfigForBackup */
/** @var array $schedulerStateForBackup */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-cloud-arrow-down-fill me-2"></i>Sauvegarde / Export admin</h3>
</div>

<?php
// ── Liste des sauvegardes ────────────────────────────────────────────────────
$retentionLabels = [
    'daily'   => (int)($schedulerConfigForBackup['retention_daily']   ?? 14),
    'weekly'  => (int)($schedulerConfigForBackup['retention_weekly']  ?? 8),
    'monthly' => (int)($schedulerConfigForBackup['retention_monthly'] ?? 12),
];
$countBySchedule = ['daily' => 0, 'weekly' => 0, 'monthly' => 0, '-' => 0];
foreach ($backupFiles as $bf) {
    $k = $bf['schedule'];
    if (isset($countBySchedule[$k])) {
        $countBySchedule[$k]++;
    }
}

$schedulerSchedules = array_filter(array_map('trim', explode(',', (string)($schedulerConfigForBackup['schedules'] ?? 'daily,weekly,monthly'))));
$schedulerEnabled = !empty($schedulerConfigForBackup['enabled']);
$lastDaily = $schedulerStateForBackup['daily']['last_run_at'] ?? null;
$lastWeekly = $schedulerStateForBackup['weekly']['last_run_at'] ?? null;
$lastMonthly = $schedulerStateForBackup['monthly']['last_run_at'] ?? null;
?>

<div class="card mb-3">
    <div class="card-header">
        <strong>Backup scheduler (dans le conteneur PHP)</strong>
    </div>
    <div class="card-body">
        <form method="POST" action="/admin/backup/scheduler" class="row g-3">
            <?= csrfField() ?>

            <div class="col-12 d-flex align-items-center gap-2">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="schedulerEnabled" name="enabled" value="1" <?= $schedulerEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="schedulerEnabled">Activer la routine</label>
                </div>
                <span class="badge <?= $schedulerEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= $schedulerEnabled ? 'ACTIVE' : 'INACTIVE' ?>
                </span>
            </div>

            <div class="col-md-4">
                <label class="form-label">Frequence</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="schDaily" name="schedules[]" value="daily" <?= in_array('daily', $schedulerSchedules, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="schDaily">Daily</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="schWeekly" name="schedules[]" value="weekly" <?= in_array('weekly', $schedulerSchedules, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="schWeekly">Weekly</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="schMonthly" name="schedules[]" value="monthly" <?= in_array('monthly', $schedulerSchedules, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="schMonthly">Monthly</label>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="runHour">Heure d'execution (0-23)</label>
                <input type="number" min="0" max="23" class="form-control" id="runHour" name="run_hour" value="<?= (int)($schedulerConfigForBackup['run_hour'] ?? 2) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="checkInterval">Intervalle de verification (sec)</label>
                <input type="number" min="60" class="form-control" id="checkInterval" name="check_interval_seconds" value="<?= (int)($schedulerConfigForBackup['check_interval_seconds'] ?? 300) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="weeklyDay">Jour weekly (1-7)</label>
                <input type="number" min="1" max="7" class="form-control" id="weeklyDay" name="weekly_day" value="<?= (int)($schedulerConfigForBackup['weekly_day'] ?? 7) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="monthlyDay">Jour monthly (1-31)</label>
                <input type="number" min="1" max="31" class="form-control" id="monthlyDay" name="monthly_day" value="<?= (int)($schedulerConfigForBackup['monthly_day'] ?? 1) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="exportModeScheduler">Mode export</label>
                <select class="form-select" id="exportModeScheduler" name="export_mode">
                    <option value="encrypted" <?= (($schedulerConfigForBackup['export_mode'] ?? 'encrypted') === 'encrypted') ? 'selected' : '' ?>>Encrypted</option>
                    <option value="plain" <?= (($schedulerConfigForBackup['export_mode'] ?? 'encrypted') === 'plain') ? 'selected' : '' ?>>Plain</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="includeSecretsScheduler" name="include_secrets" value="1" <?= !empty($schedulerConfigForBackup['include_secrets']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="includeSecretsScheduler">Inclure les secrets</label>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="passphraseScheduler">Passphrase (mode encrypted)</label>
                <input type="password" class="form-control" id="passphraseScheduler" name="passphrase" value="<?= htmlspecialchars((string)($schedulerConfigForBackup['passphrase'] ?? '')) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label" for="retDaily">Retention daily</label>
                <input type="number" min="1" class="form-control" id="retDaily" name="retention_daily" value="<?= (int)($schedulerConfigForBackup['retention_daily'] ?? 14) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="retWeekly">Retention weekly</label>
                <input type="number" min="1" class="form-control" id="retWeekly" name="retention_weekly" value="<?= (int)($schedulerConfigForBackup['retention_weekly'] ?? 8) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="retMonthly">Retention monthly</label>
                <input type="number" min="1" class="form-control" id="retMonthly" name="retention_monthly" value="<?= (int)($schedulerConfigForBackup['retention_monthly'] ?? 12) ?>">
            </div>

            <div class="col-12 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Dernières exécutions :
                    daily <?= htmlspecialchars(substr((string)($lastDaily ?? '-'), 0, 19)) ?>,
                    weekly <?= htmlspecialchars(substr((string)($lastWeekly ?? '-'), 0, 19)) ?>,
                    monthly <?= htmlspecialchars(substr((string)($lastMonthly ?? '-'), 0, 19)) ?>
                </small>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Enregistrer la configuration
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong><i class="bi bi-archive me-1"></i>Sauvegardes (<?= count($backupFiles) ?>)</strong>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach (['daily', 'weekly', 'monthly'] as $sch): ?>
                <form method="POST" action="/admin/backup/rotate" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="schedule" value="<?= $sch ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                            title="Conserver uniquement les <?= $retentionLabels[$sch] ?> derniers fichiers <?= $sch ?>">
                        <i class="bi bi-arrow-repeat me-1"></i>Rotation <?= ucfirst($sch) ?>
                        <span class="badge text-bg-light ms-1"><?= $countBySchedule[$sch] ?> / <?= $retentionLabels[$sch] ?></span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (empty($backupFiles)): ?>
        <div class="card-body text-muted">
            <i class="bi bi-inbox me-1"></i>Aucune sauvegarde trouvée dans le dossier <code>/backups</code>.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fichier</th>
                        <th>Type</th>
                        <th>Période</th>
                        <th>Mode</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupFiles as $bf): ?>
                        <tr>
                            <td><code class="small"><?= htmlspecialchars($bf['name']) ?></code></td>
                            <td>
                                <?php if ($bf['type'] === 'auto'): ?>
                                    <span class="badge text-bg-info">Auto</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Manuel</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($bf['schedule'] !== '-'): ?>
                                    <span class="badge text-bg-light border"><?= htmlspecialchars(ucfirst($bf['schedule'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($bf['mode'] === 'encrypted'): ?>
                                    <i class="bi bi-lock-fill text-success"></i> <small>Chiffré</small>
                                <?php else: ?>
                                    <i class="bi bi-unlock text-warning"></i> <small>Plain</small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= number_format($bf['size'] / 1024, 1) ?> Ko</small></td>
                            <td><small class="text-muted"><?= date('d/m/Y H:i', $bf['mtime']) ?></small></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="/admin/backup/download?filename=<?= urlencode($bf['name']) ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Télécharger"
                                       download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form method="POST" action="/admin/backup/delete" class="d-inline"
                                          onsubmit="return confirm('Supprimer ce fichier de sauvegarde ?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($bf['name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$defaultSaveMode       = (string)($schedulerConfigForBackup['export_mode'] ?? 'encrypted');
$defaultSavePassphrase = (string)($schedulerConfigForBackup['passphrase']  ?? '');
$defaultIncludeSecrets = !empty($schedulerConfigForBackup['include_secrets']);
?>

<div class="card mb-3">
    <div class="card-header">
        <strong><i class="bi bi-play-circle me-1"></i>Lancer un backup manuel</strong>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            La sauvegarde sera enregistrée dans le dossier <code>/backups</code> du serveur
            sous la forme <code>ddsafe-backup-YYYYMMDD-HHmmss-*.json</code>.
        </p>
        <form method="POST" action="/admin/backup/save" class="row g-3" id="manual-backup-form">
            <?= csrfField() ?>

            <div class="col-md-4">
                <label class="form-label" for="mb-mode">Mode</label>
                <select class="form-select" name="export_mode" id="mb-mode">
                    <option value="encrypted" <?= $defaultSaveMode === 'encrypted' ? 'selected' : '' ?>>Chiffré (AES-256-CBC)</option>
                    <option value="plain"     <?= $defaultSaveMode === 'plain'     ? 'selected' : '' ?>>Non chiffré (JSON)</option>
                </select>
            </div>

            <div class="col-md-5" id="mb-pass-group">
                <label class="form-label" for="mb-passphrase">Phrase de passe</label>
                <input type="password" class="form-control" id="mb-passphrase" name="passphrase"
                       minlength="8"
                       placeholder="≥ 8 caractères"
                       value="<?= htmlspecialchars($defaultSavePassphrase) ?>">
                <small class="text-muted">Pré-remplie depuis la config du scheduler.</small>
            </div>

            <div class="col-md-3 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mb-secrets" name="include_secrets" value="1"
                           <?= $defaultIncludeSecrets ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mb-secrets">Inclure les secrets</label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-device-hdd me-1"></i>Sauvegarder sur le serveur
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted mb-3">
            Vous pouvez exporter en mode chiffré (recommandé) ou en mode JSON non chiffré.
        </p>

        <form method="POST" action="/admin/backup/export" class="mb-4">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label">Mode d'export</label>
                <select class="form-select" name="export_mode" id="backup-export-mode">
                    <option value="encrypted" selected>Chiffré (AES-256-CBC)</option>
                    <option value="plain">Non chiffré (JSON)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Phrase de passe de chiffrement *</label>
                <input type="password" name="passphrase" id="backup-passphrase" class="form-control" minlength="8" required>
                <small class="text-muted" id="backup-passphrase-help">Conservez cette phrase de passe: elle sera nécessaire pour déchiffrer l'export.</small>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="include-secrets" name="include_secrets" value="1">
                <label class="form-check-label" for="include-secrets">
                    Inclure les secrets (désactivé par défaut)
                </label>
            </div>

            <div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Recommandé: garder les secrets exclus pour les sauvegardes de routine.
            </div>

            <button type="submit" class="btn btn-accent">
                <i class="bi bi-download me-1"></i>Télécharger l'export chiffré
            </button>
        </form>

        <hr>

        <h6 class="mb-2"><i class="bi bi-shield-check me-1"></i>Vérifier un backup</h6>
        <form method="POST" action="/admin/backup/verify" enctype="multipart/form-data" class="mb-4">
            <?= csrfField() ?>
            <div class="mb-2">
                <input type="file" name="backup_file" class="form-control" accept=".json" required>
            </div>
            <div class="mb-2">
                <input type="password" name="verify_passphrase" class="form-control" placeholder="Phrase de passe (si backup chiffré)">
            </div>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-check2-circle me-1"></i>Vérifier
            </button>
        </form>

        <h6 class="mb-2"><i class="bi bi-box-arrow-in-down me-1"></i>Importer un backup</h6>
        <form method="POST" action="/admin/backup/import" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="mb-2">
                <input type="file" name="backup_file" class="form-control" accept=".json" required>
            </div>
            <div class="mb-2">
                <input type="password" name="import_passphrase" class="form-control" placeholder="Phrase de passe (si backup chiffré)">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="overwrite-existing" name="overwrite" value="1">
                <label class="form-check-label" for="overwrite-existing">Mettre à jour les OTP existants (même nom/issuer/collection)</label>
            </div>
            <button type="submit" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-down me-1"></i>Importer
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Export (téléchargement navigateur)
    var mode = document.getElementById('backup-export-mode');
    var pass = document.getElementById('backup-passphrase');
    var help = document.getElementById('backup-passphrase-help');
    var btn = document.querySelector('form[action="/admin/backup/export"] button[type="submit"]');
    if (mode && pass && help && btn) {
        function sync() {
            var encrypted = mode.value === 'encrypted';
            pass.required = encrypted;
            pass.disabled = !encrypted;
            help.textContent = encrypted
                ? 'Conservez cette phrase de passe: elle sera nécessaire pour déchiffrer l\'export.'
                : 'Mode non chiffré: la phrase de passe n\'est pas utilisée.';
            btn.innerHTML = encrypted
                ? '<i class="bi bi-download me-1"></i>Télécharger l\'export chiffré'
                : '<i class="bi bi-download me-1"></i>Télécharger l\'export non chiffré';
        }
        mode.addEventListener('change', sync);
        sync();
    }

    // Backup manuel (enregistrement serveur)
    var mbMode      = document.getElementById('mb-mode');
    var mbPass      = document.getElementById('mb-passphrase');
    var mbPassGroup = document.getElementById('mb-pass-group');
    if (mbMode && mbPass && mbPassGroup) {
        function syncManual() {
            var enc = mbMode.value === 'encrypted';
            mbPass.required = enc;
            mbPass.disabled = !enc;
            mbPassGroup.style.opacity = enc ? '1' : '0.4';
        }
        mbMode.addEventListener('change', syncManual);
        syncManual();
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
