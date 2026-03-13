<?php
/** @var string $pageTitle */
/** @var bool $zipExists */
/** @var float $zipSizeMb */
/** @var string $extensionDownloadUrl */
/** @var string $extensionConfiguredAppUrl */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-puzzle me-2"></i>Extension Chrome / Edge (lecture seule)</h3>
</div>

<div class="card mb-3">
    <div class="card-body">
        <p class="mb-2">Installez l'extension officielle en lecture seule pour afficher les OTP depuis l'onglet DDSafe actif.</p>
        <ul class="mb-3">
            <li>Aucune modification de données</li>
            <li>Aucun import / suppression</li>
            <li>Lecture uniquement des codes visibles</li>
        </ul>

        <?php if ($zipExists): ?>
            <a class="btn btn-accent" href="<?= htmlspecialchars($extensionDownloadUrl) ?>">
                <i class="bi bi-download me-1"></i>Télécharger l'extension (.zip)
            </a>
            <span class="ms-2 text-muted">Taille: <?= htmlspecialchars((string)$zipSizeMb) ?> Mo</span>
            <div class="mt-2 small" style="color:var(--text-muted)">
                URL app préconfigurée : <a href="<?= htmlspecialchars($extensionConfiguredAppUrl) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($extensionConfiguredAppUrl) ?></a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                Package non disponible sur le serveur.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Installation Chrome</strong></div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Téléchargez le fichier ZIP ci-dessus.</li>
            <li>Décompressez-le dans un dossier local.</li>
            <li>Ouvrez <code>chrome://extensions</code>.</li>
            <li>Activez le <strong>Mode développeur</strong>.</li>
            <li>Cliquez <strong>Charger l'extension non empaquetée</strong>.</li>
            <li>Sélectionnez le dossier extrait.</li>
        </ol>
    </div>
</div>

<div class="card">
    <div class="card-header"><strong>Installation Edge</strong></div>
    <div class="card-body">
        <ol class="mb-0">
            <li>Téléchargez le fichier ZIP ci-dessus.</li>
            <li>Décompressez-le dans un dossier local.</li>
            <li>Ouvrez <code>edge://extensions</code>.</li>
            <li>Activez le <strong>Mode développeur</strong>.</li>
            <li>Cliquez <strong>Charger l'extension non empaquetée</strong>.</li>
            <li>Sélectionnez le dossier extrait.</li>
        </ol>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
