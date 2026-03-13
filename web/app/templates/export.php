<?php
/** @var array $codes */
/** @var array $qrCodes */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-qr-code me-2"></i>Exporter des codes OTP</h3>
    <div class="d-flex gap-2">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
        <a href="/otp" class="btn btn-ghost btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
    </div>
</div>

<?php if (empty($codes)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Aucun code sélectionné pour l'export. <a href="/otp">Retourner à la liste</a>.
    </div>
<?php else: ?>
    <div class="row" id="export-grid">
        <?php foreach ($codes as $code): ?>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card h-100 export-card">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= htmlspecialchars($code['name']) ?></h5>
                        <?php if (!empty($code['issuer'])): ?>
                            <p class="text-muted mb-3"><?= htmlspecialchars($code['issuer']) ?></p>
                        <?php endif; ?>

                        <div class="qr-code-container mb-3">
                            <?= $qrCodes[$code['id']] ?? '' ?>
                        </div>

                        <div class="small text-muted">
                            <span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($code['type'] ?? 'TOTP')) ?></span>
                            <span class="badge bg-info"><?= htmlspecialchars($code['algorithm'] ?? 'SHA1') ?></span>
                            <span class="badge bg-light text-dark"><?= (int)($code['digits'] ?? 6) ?> chiffres</span>
                            <span class="badge bg-light text-dark"><?= (int)($code['period'] ?? 30) ?>s</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    .export-card .qr-code-container svg {
        max-width: 200px;
        max-height: 200px;
        width: 100%;
        height: auto;
    }
    @media print {
        nav, .btn, footer { display: none !important; }
        .export-card { break-inside: avoid; border: 1px solid #ccc !important; }
    }
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
