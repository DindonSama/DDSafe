<?php
/** @var array $userTenants */
/** @var array $otpWritableTenants */
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-qr-code-scan me-2"></i>Importer un code OTP</h3>
</div>
<p style="color:var(--text-muted)">Scannez un QR code ou collez manuellement l'URI otpauth://</p>

<div class="row">
    <!-- QR Scanner -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-camera me-2"></i>Scanner un QR Code</h5>
            </div>
            <div class="card-body">
                <div id="qr-reader" class="mb-3"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="btn-start-scan">
                        <i class="bi bi-camera-video me-1"></i>Démarrer la caméra
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="btn-upload-qr">
                        <i class="bi bi-upload me-1"></i>Charger une image
                    </button>
                    <input type="file" id="qr-file-input" accept="image/*" class="d-none">
                </div>
                <div id="qr-scan-result" class="mt-3 d-none">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-1"></i>
                        QR Code détecté !
                        <div class="mt-1 small font-monospace text-break" id="qr-detected-uri"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import form -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-input-cursor-text me-2"></i>Saisie manuelle</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/otp/import" id="importForm">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">URI otpauth:// *</label>
                        <textarea name="otp_uri" id="otp_uri" class="form-control font-monospace" rows="6"
                                  required placeholder="otpauth://totp/GitHub:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=GitHub&#10;otpauth://totp/Google:user@gmail.com?secret=ABCDEFGHIJKLMNOP&issuer=Google&#10;..."><?= htmlspecialchars($prefillUri ?? '') ?></textarea>
                        <small class="text-muted">Collez une ou plusieurs URI otpauth:// (une par ligne), ou scannez un QR code ci-contre</small>
                    </div>

                    <!-- Preview -->
                    <div id="import-preview" class="mb-3 d-none">
                        <div class="card" style="background:var(--surface-2)">
                            <div class="card-body py-2">
                                <h6 class="mb-2"><span id="preview-count">0</span> code(s) détecté(s) :</h6>
                                <ul id="preview-list" class="list-unstyled mb-0" style="max-height:160px;overflow-y:auto;font-size:.85rem"></ul>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <?php if ($config['personal_codes_enabled'] ?? false): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="import_personal"
                                   name="is_personal" value="1"
                                   onchange="var ts=document.getElementById('tenant-select-import'); var sel=ts.querySelector('select'); if(this.checked){ts.style.display='none'; sel.required=false;}else{ts.style.display='block'; sel.required=true;}">
                            <label class="form-check-label" for="import_personal">Code personnel</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3" id="tenant-select-import"<?php if (count($otpWritableTenants) === 1): ?> style="display:none"<?php endif; ?>>
                        <label class="form-label">Tenant cible *</label>
                        <select name="tenant" class="form-select"<?php if (count($otpWritableTenants) !== 1): ?> required<?php endif; ?>>
                            <?php if (count($otpWritableTenants) === 1): ?>
                                <option value="<?= htmlspecialchars($otpWritableTenants[0]['id']) ?>" selected><?= htmlspecialchars($otpWritableTenants[0]['name']) ?></option>
                            <?php else: ?>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($otpWritableTenants as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($otpWritableTenants)): ?>
                            <small class="text-muted">Aucun tenant disponible en ecriture OTP pour votre role.</small>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-success w-100" id="import-submit-btn" disabled>
                        <i class="bi bi-download me-1"></i>Importer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pre-fill: trigger preview if URI was passed via ?uri= parameter
    const prefilled = document.getElementById('otp_uri').value.trim();
    if (prefilled.length > 0) {
        parseAndPreview(prefilled);
    }

    let html5QrCode = null;

    // Start camera scan
    document.getElementById('btn-start-scan').addEventListener('click', function() {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
            this.innerHTML = '<i class="bi bi-camera-video me-1"></i>Démarrer la caméra';
            return;
        }

        html5QrCode = new Html5Qrcode("qr-reader");
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onQrDetected
        ).catch(err => {
            const msg = String(err);
            if (msg.includes('not supported') || msg.includes('getUserMedia')) {
                alert("La caméra nécessite une connexion HTTPS. Utilisez le bouton « Charger une image » pour scanner un QR code depuis un fichier.");
            } else {
                alert("Impossible d'accéder à la caméra : " + msg);
            }
        });
        this.innerHTML = '<i class="bi bi-stop-fill me-1"></i>Arrêter la caméra';
    });

    // Upload file
    document.getElementById('btn-upload-qr').addEventListener('click', function() {
        document.getElementById('qr-file-input').click();
    });

    document.getElementById('qr-file-input').addEventListener('change', function(e) {
        if (e.target.files.length === 0) return;
        const tempQr = new Html5Qrcode("qr-reader");
        tempQr.scanFile(e.target.files[0], true).then(onQrDetected).catch(err => {
            alert("Impossible de lire le QR code de cette image.");
        });
    });

    function onQrDetected(decodedText) {
        // Append scanned URI to textarea, one per line
        const ta       = document.getElementById('otp_uri');
        const existing = ta.value.trim();
        ta.value = existing ? existing + '\n' + decodedText : decodedText;
        document.getElementById('qr-detected-uri').textContent = decodedText;
        document.getElementById('qr-scan-result').classList.remove('d-none');

        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop();
            document.getElementById('btn-start-scan').innerHTML =
                '<i class="bi bi-camera-video me-1"></i>Démarrer la caméra';
        }

        parseAndPreview(ta.value);
    }

    // Parse multi-URI and show preview
    document.getElementById('otp_uri').addEventListener('input', function() {
        parseAndPreview(this.value);
    });

    function parseAndPreview(text) {
        const uris      = text.split('\n').map(l => l.trim()).filter(l => l.startsWith('otpauth://'));
        const previewEl = document.getElementById('import-preview');
        const submitBtn = document.getElementById('import-submit-btn');

        if (uris.length === 0) {
            previewEl.classList.add('d-none');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer';
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/api/otp/parse-bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ uris: uris })
        })
        .then(r => r.json())
        .then(data => {
            const results = data.results || [];
            const list    = document.getElementById('preview-list');
            list.innerHTML = '';
            results.forEach(r => {
                const li     = document.createElement('li');
                li.className = 'mb-1';
                const name   = r.data.name   || '?';
                const issuer = r.data.issuer ? ' — ' + r.data.issuer : '';
                li.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i><strong>' + name + '</strong>' + issuer;
                list.appendChild(li);
            });
            document.getElementById('preview-count').textContent = results.length;
            previewEl.classList.toggle('d-none', results.length === 0);
            submitBtn.disabled = results.length === 0;
            const n = results.length;
            submitBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer ' + n + ' code' + (n > 1 ? 's' : '');
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
