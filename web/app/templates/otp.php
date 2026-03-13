<?php
/** @var array $personalCodes */
/** @var array $tenantCodes */
/** @var array $userTenants */
/** @var array $otpWritableTenants */
/** @var array $tenantManageOtpMap */
/** @var bool $canCreateOtp */
/** @var string $currentTenantId */
/** @var string $currentTenantName */
/** @var string $search */
/** @var string $currentScope */
/** @var bool $showPersonalCodes */
/** @var bool $showTenantCodes */
$iconColors = ['#5865f2','#3ba55c','#ed4245','#faa61a','#eb459e','#57f287','#5dadec','#fee75c'];
ob_start();
?>

<div class="page-header">
    <h3><i class="bi bi-key-fill me-2"></i>Codes OTP</h3>
    <div class="d-flex gap-2 align-items-center">
        <?php if ($canCreateOtp): ?>
            <a href="/otp/import" class="btn btn-ghost btn-sm">
                <i class="bi bi-qr-code-scan me-1"></i>Importer QR
            </a>
            <button class="btn btn-ghost btn-sm" data-bs-toggle="modal" data-bs-target="#importUriModal">
                <i class="bi bi-link-45deg me-1"></i>Importer URI
            </button>
            <button class="btn btn-accent btn-sm" data-bs-toggle="modal" data-bs-target="#addOtpModal">
                <i class="bi bi-plus-lg me-1"></i>Ajouter
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Tenant filter bar -->
<div class="tenant-filter-bar mb-3">
    <form method="POST" action="/tenants/select" id="tenantFilterForm">
        <?= csrfField() ?>
        <input type="hidden" name="redirect" id="tenantFilterRedirect" value="/otp">
        <input type="hidden" name="tenant_id" id="tenantFilterValue" value="">
        <div class="tenant-pills">
            <button type="button" class="tenant-pill <?= empty($currentTenantId) ? 'active' : '' ?>"
                    onclick="document.getElementById('tenantFilterValue').value='';document.getElementById('tenantFilterRedirect').value='/otp';document.getElementById('tenantFilterForm').submit();">
                <i class="bi bi-collection me-1"></i>Tous
            </button>
            <?php if ($config['personal_codes_enabled'] ?? false): ?>
                <button type="button" class="tenant-pill <?= empty($currentTenantId) && (($currentScope ?? 'all') === 'personal') ? 'active' : '' ?>"
                        onclick="document.getElementById('tenantFilterValue').value='';document.getElementById('tenantFilterRedirect').value='/otp?scope=personal';document.getElementById('tenantFilterForm').submit();">
                    <i class="bi bi-person-lock me-1"></i>Personnels
                </button>
            <?php endif; ?>
            <?php foreach ($userTenants as $t): ?>
                <button type="button" class="tenant-pill <?= ($t['id'] === $currentTenantId) ? 'active' : '' ?>"
                        onclick="document.getElementById('tenantFilterValue').value='<?= htmlspecialchars($t['id']) ?>';document.getElementById('tenantFilterRedirect').value='/otp';document.getElementById('tenantFilterForm').submit();">
                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($t['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<div class="mb-3">
    <label for="otp-search" class="visually-hidden">Rechercher un code OTP</label>
    <input type="text" id="otp-search" class="form-control" placeholder="Rechercher un code OTP..."
           value="<?= htmlspecialchars($search ?? '') ?>" autofocus>
</div>

<!-- Personal codes -->
    <?php if ($showPersonalCodes): ?>
    <div class="section-label">
        <i class="bi bi-person-lock"></i> Mes codes personnels
        <span class="badge bg-secondary"><?= count($personalCodes) ?></span>
    </div>

    <?php if (empty($personalCodes)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-key"></i></div>
            <p>Aucun code personnel.</p>
        </div>
    <?php else: ?>
        <div class="otp-grid mb-4" id="personal-codes">
            <?php foreach ($personalCodes as $i => $code):
                $color = $iconColors[$i % count($iconColors)];
                $letter = strtoupper(mb_substr($code['issuer'] ?: $code['name'], 0, 1));
            ?>
                <div class="otp-account otp-item"
                     role="button"
                     tabindex="0"
                     aria-label="Copier le code OTP de <?= htmlspecialchars($code['name']) ?>"
                     data-name="<?= htmlspecialchars(strtolower($code['name'])) ?>"
                     data-issuer="<?= htmlspecialchars(strtolower($code['issuer'] ?? '')) ?>"
                     onclick="copyOtpCode(this, '<?= htmlspecialchars($code['id']) ?>')" onkeydown="handleOtpCardKey(event, this, '<?= htmlspecialchars($code['id']) ?>')">

                    <div class="account-actions" onclick="event.stopPropagation()">
                        <div class="dropdown d-inline">
                            <button type="button" class="btn-action" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button type="button" class="dropdown-item btn-edit-otp"
                                            data-id="<?= htmlspecialchars($code['id']) ?>"
                                            data-name="<?= htmlspecialchars($code['name']) ?>"
                                            data-issuer="<?= htmlspecialchars($code['issuer'] ?? '') ?>"
                                            data-secret="<?= htmlspecialchars($code['secret'] ?? '') ?>"
                                            data-algorithm="<?= htmlspecialchars($code['algorithm'] ?? 'SHA1') ?>"
                                            data-digits="<?= htmlspecialchars((string)($code['digits'] ?? 6)) ?>"
                                            data-period="<?= htmlspecialchars((string)($code['period'] ?? 30)) ?>">
                                        <i class="bi bi-pencil me-2"></i>Modifier
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/otp/export?ids=<?= htmlspecialchars($code['id']) ?>">
                                        <i class="bi bi-qr-code me-2"></i>Exporter QR
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (!empty($currentUser['is_app_admin'])): ?>
                                <li>
                                    <button type="button" class="dropdown-item text-danger btn-delete-otp"
                                            data-id="<?= htmlspecialchars($code['id']) ?>"
                                            data-name="<?= htmlspecialchars($code['name']) ?>">
                                        <i class="bi bi-trash me-2"></i>Supprimer
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="account-name"><?= htmlspecialchars($code['name']) ?></div>
                    <div class="account-issuer"><?= htmlspecialchars($code['issuer'] ?? '') ?>&nbsp;</div>
                    <div class="otp-value" data-otp-id="<?= htmlspecialchars($code['id']) ?>">
                        <span class="code-display">···  ···</span>
                    </div>
                    <div class="countdown-ring" data-timer-id="<?= htmlspecialchars($code['id']) ?>">
                        <svg viewBox="0 0 36 36">
                            <circle class="ring-bg" cx="18" cy="18" r="15.5"/>
                            <circle class="ring-fg" cx="18" cy="18" r="15.5"
                                    stroke-dasharray="97.39" stroke-dashoffset="0"/>
                        </svg>
                        <span class="ring-text">--</span>
                    </div>
                    <div class="copy-toast"><i class="bi bi-check-lg me-1"></i>Copié</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Tenant codes -->
    <?php if ($showTenantCodes && !empty($tenantCodes)): ?>
    <div class="section-label mt-4">
        <i class="bi bi-building"></i> <?= htmlspecialchars($currentTenantName) ?>
        <span class="badge bg-success"><?= count($tenantCodes) ?></span>
    </div>

    <div class="otp-grid mb-4" id="tenant-codes">
        <?php foreach ($tenantCodes as $i => $code):
            $color = $iconColors[($i + 3) % count($iconColors)];
            $letter = strtoupper(mb_substr($code['issuer'] ?: $code['name'], 0, 1));
            $tenantId = (string)($code['tenant'] ?? '');
            $canManageThisTenant = !empty($tenantManageOtpMap[$tenantId]);
            $canDeleteTenantCode = !empty($currentUser['is_app_admin']) || $canManageThisTenant;
        ?>
            <div class="otp-account is-tenant otp-item"
                 role="button"
                 tabindex="0"
                 aria-label="Copier le code OTP de <?= htmlspecialchars($code['name']) ?>"
                 data-name="<?= htmlspecialchars(strtolower($code['name'])) ?>"
                 data-issuer="<?= htmlspecialchars(strtolower($code['issuer'] ?? '')) ?>"
                 onclick="copyOtpCode(this, '<?= htmlspecialchars($code['id']) ?>')" onkeydown="handleOtpCardKey(event, this, '<?= htmlspecialchars($code['id']) ?>')">

                <div class="account-actions" onclick="event.stopPropagation()">
                    <div class="dropdown d-inline">
                        <button type="button" class="btn-action" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($canDeleteTenantCode): ?>
                            <li>
                                <button type="button" class="dropdown-item btn-edit-otp"
                                        data-id="<?= htmlspecialchars($code['id']) ?>"
                                        data-name="<?= htmlspecialchars($code['name']) ?>"
                                        data-issuer="<?= htmlspecialchars($code['issuer'] ?? '') ?>"
                                        data-secret="<?= htmlspecialchars($code['secret'] ?? '') ?>"
                                        data-algorithm="<?= htmlspecialchars($code['algorithm'] ?? 'SHA1') ?>"
                                        data-digits="<?= htmlspecialchars((string)($code['digits'] ?? 6)) ?>"
                                        data-period="<?= htmlspecialchars((string)($code['period'] ?? 30)) ?>">
                                    <i class="bi bi-pencil me-2"></i>Modifier
                                </button>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="/otp/export?ids=<?= htmlspecialchars($code['id']) ?>">
                                    <i class="bi bi-qr-code me-2"></i>Exporter QR
                                </a>
                            </li>
                            <?php if ($canDeleteTenantCode): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item text-danger btn-delete-otp"
                                        data-id="<?= htmlspecialchars($code['id']) ?>"
                                        data-name="<?= htmlspecialchars($code['name']) ?>">
                                    <i class="bi bi-trash me-2"></i>Supprimer
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="account-name"><?= htmlspecialchars($code['name']) ?></div>
                <div class="account-issuer"><?= htmlspecialchars($code['issuer'] ?? '') ?>&nbsp;</div>
                <div class="otp-value" data-otp-id="<?= htmlspecialchars($code['id']) ?>">
                    <span class="code-display">···  ···</span>
                </div>
                <div class="countdown-ring" data-timer-id="<?= htmlspecialchars($code['id']) ?>">
                    <svg viewBox="0 0 36 36">
                        <circle class="ring-bg" cx="18" cy="18" r="15.5"/>
                        <circle class="ring-fg" cx="18" cy="18" r="15.5"
                                stroke-dasharray="97.39" stroke-dashoffset="0"/>
                    </svg>
                    <span class="ring-text">--</span>
                </div>
                <div class="copy-toast"><i class="bi bi-check-lg me-1"></i>Copié</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>


<?php if ($canCreateOtp): ?>
<!-- Import URI Modal -->
<div class="modal fade" id="importUriModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/import" id="importUriForm">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>Importer par URI otpauth://</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">URI otpauth:// *</label>
                        <textarea name="otp_uri" id="modal-otp-uri" class="form-control font-monospace" rows="5"
                                  required placeholder="otpauth://totp/GitHub:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=GitHub&#10;otpauth://totp/Google:user@gmail.com?secret=ABCDEFGHIJKLMNOP&issuer=Google"></textarea>
                        <small class="text-muted">Une ou plusieurs URI (une par ligne)</small>
                    </div>
                    <div id="modal-import-preview" class="mb-3 d-none">
                        <div class="card" style="background:var(--surface-2)">
                            <div class="card-body py-2">
                                <h6 class="mb-2"><span id="modal-preview-count">0</span> code(s) détecté(s) :</h6>
                                <ul id="modal-preview-list" class="list-unstyled mb-0" style="max-height:130px;overflow-y:auto;font-size:.85rem"></ul>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:var(--border-color)">
                    <?php if ($config['personal_codes_enabled'] ?? false): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal_is_personal" name="is_personal" value="1"
                                   onchange="var ts=document.getElementById('modal-tenant-select'); var sel=ts.querySelector('select'); if(this.checked){ts.style.display='none'; sel.required=false;}else{ts.style.display='block'; sel.required=true;}">
                            <label class="form-check-label" for="modal_is_personal">Code personnel</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-0" id="modal-tenant-select"<?php if (count($otpWritableTenants) === 1): ?> style="display:none"<?php endif; ?>>
                        <label class="form-label">Tenant cible *</label>
                        <select name="tenant" class="form-select"<?php if (count($otpWritableTenants) !== 1): ?> required<?php endif; ?> id="modal-tenant-sel">
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
                            <small class="text-muted">Aucun tenant disponible en écriture OTP pour votre rôle.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success" id="modal-import-btn" disabled>
                        <i class="bi bi-download me-1"></i>Importer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
    const uriInput  = document.getElementById('modal-otp-uri');
    const preview   = document.getElementById('modal-import-preview');
    const importBtn = document.getElementById('modal-import-btn');

    function parseAndShowPreview(text) {
        const uris = text.split('\n').map(l => l.trim()).filter(l => l.startsWith('otpauth://'));
        if (uris.length === 0) {
            preview.classList.add('d-none');
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer';
            return;
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/api/otp/parse-bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ uris })
        })
        .then(r => r.json())
        .then(data => {
            const results = data.results || [];
            const list    = document.getElementById('modal-preview-list');
            list.innerHTML = '';
            results.forEach(r => {
                const li     = document.createElement('li');
                li.className = 'mb-1';
                const name   = r.data.name   || '?';
                const issuer = r.data.issuer ? ' — ' + r.data.issuer : '';
                li.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i><strong>' + name + '</strong>' + issuer;
                list.appendChild(li);
            });
            document.getElementById('modal-preview-count').textContent = results.length;
            preview.classList.toggle('d-none', results.length === 0);
            importBtn.disabled = results.length === 0;
            const n = results.length;
            importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer ' + n + ' code' + (n > 1 ? 's' : '');
        })
        .catch(() => { preview.classList.add('d-none'); importBtn.disabled = true; });
    }

    if (uriInput) {
        uriInput.addEventListener('input', function() { parseAndShowPreview(this.value); });
        uriInput.addEventListener('paste', function() {
            setTimeout(() => parseAndShowPreview(this.value), 50);
        });
    }

    // Reset modal on close
    document.getElementById('importUriModal')?.addEventListener('hidden.bs.modal', function() {
        if (uriInput) uriInput.value = '';
        preview.classList.add('d-none');
        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer';
    });
})();
</script>

<!-- Add OTP Modal -->
<div class="modal fade" id="addOtpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/add">
            <?= csrfField() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Ajouter un code OTP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Ex: GitHub">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Émetteur (issuer)</label>
                        <input type="text" name="issuer" class="form-control" placeholder="Ex: GitHub Inc.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Secret (Base32) *</label>
                        <input type="text" name="secret" class="form-control" required
                               placeholder="JBSWY3DPEHPK3PXP" pattern="[A-Za-z2-7=]+">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Algorithme</label>
                            <select name="algorithm" class="form-select">
                                <option value="SHA1" selected>SHA1</option>
                                <option value="SHA256">SHA256</option>
                                <option value="SHA512">SHA512</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Chiffres</label>
                            <select name="digits" class="form-select">
                                <option value="6" selected>6</option>
                                <option value="8">8</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Période (s)</label>
                            <input type="number" name="period" class="form-control" value="30" min="15" max="120">
                        </div>
                    </div>
                    <hr style="border-color:var(--border-color)">
                    <?php if ($config['personal_codes_enabled'] ?? false): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_personal" name="is_personal"
                                   value="1" onchange="var ts=document.getElementById('tenant-select-add'); var sel=ts.querySelector('select'); if(this.checked){ts.style.display='none'; sel.required=false;}else{ts.style.display='block'; sel.required=true;}">
                            <label class="form-check-label" for="is_personal">Code personnel</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3" id="tenant-select-add">
                        <label class="form-label">Tenant *</label>
                        <select name="tenant" class="form-select" required>
                            <option value="">-- Choisir un tenant --</option>
                            <?php foreach ($otpWritableTenants as $t): ?>
                                <option value="<?= htmlspecialchars($t['id']) ?>">
                                    <?= htmlspecialchars($t['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($otpWritableTenants)): ?>
                            <small class="text-muted">Aucun tenant avec permission d'ecriture OTP pour votre role.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent">
                        <i class="bi bi-plus-lg me-1"></i>Ajouter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Edit OTP Modal -->
<div class="modal fade" id="editOtpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/edit">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="edit-otp-id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Modifier le code OTP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="name" id="edit-otp-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Émetteur</label>
                        <input type="text" name="issuer" id="edit-otp-issuer" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Secret (lecture seule)</label>
                        <div class="input-group">
                            <input type="password" name="secret" id="edit-otp-secret" class="form-control"
                                   readonly
                                   autocomplete="off"
                                   placeholder="Secret masqué"
                                   pattern="[A-Za-z2-7=]+">
                            <button type="button" class="btn btn-outline-secondary" id="toggle-edit-otp-secret"
                                    aria-label="Afficher le secret" aria-pressed="false">
                                <i class="bi bi-eye me-1"></i>Afficher
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Algorithme</label>
                            <select name="algorithm" id="edit-otp-algorithm" class="form-select">
                                <option value="SHA1">SHA1</option>
                                <option value="SHA256">SHA256</option>
                                <option value="SHA512">SHA512</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Chiffres</label>
                            <select name="digits" id="edit-otp-digits" class="form-select">
                                <option value="6">6</option>
                                <option value="8">8</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Période (s)</label>
                            <input type="number" name="period" id="edit-otp-period" class="form-control" value="30" min="15" max="120">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent">Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete OTP Modal -->
<div class="modal fade" id="deleteOtpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/delete">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="delete-otp-id">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="color:var(--danger)"><i class="bi bi-trash me-2"></i>Supprimer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer <strong id="delete-otp-name"></strong> ?
                    <br><small style="color:var(--text-muted)">Le code sera placé dans la corbeille.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
