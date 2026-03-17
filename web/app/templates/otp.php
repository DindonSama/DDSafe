<?php
/** @var array $personalCodes */
/** @var array $tenantCodes */
/** @var array $userTenants */
/** @var array $otpWritableTenants */
/** @var array $tenantManageOtpMap */
/** @var bool $canCreateOtp */
/** @var string $currentTenantId */
/** @var string $currentTenantName */
/** @var array $tenantGroups */
/** @var array $tenantFolders */
/** @var string $currentFolderId */
/** @var string $currentFolderName */
/** @var array $rootTenantCodes */
/** @var bool $currentTenantCanManageOtp */
/** @var string $search */
/** @var string $currentScope */
/** @var bool $showPersonalCodes */
/** @var bool $showTenantCodes */
$iconColors = ['#5865f2','#3ba55c','#ed4245','#faa61a','#eb459e','#57f287','#5dadec','#fee75c'];
$otpReturnPath = !empty($currentFolderId) ? '/otp?folder=' . rawurlencode($currentFolderId) : '/otp';

if (!function_exists('otpIssuerIcon')) {
    function otpIssuerIcon(string $issuer): ?string {
        if ($issuer === '') return null;
        $k = strtolower($issuer);
        static $map = [
            'github'      => 'bi-github',
            'gitlab'      => 'bi-gitlab',
            'bitbucket'   => 'bi-bitbucket',
            'google'      => 'bi-google',
            'microsoft'   => 'bi-microsoft',
            'windows'     => 'bi-windows',
            'azure'       => 'bi-microsoft',
            'apple'       => 'bi-apple',
            'icloud'      => 'bi-apple',
            'amazon'      => 'bi-amazon',
            'aws'         => 'bi-amazon',
            'discord'     => 'bi-discord',
            'facebook'    => 'bi-facebook',
            'meta'        => 'bi-meta',
            'instagram'   => 'bi-instagram',
            'twitter'     => 'bi-twitter-x',
            'twitch'      => 'bi-twitch',
            'youtube'     => 'bi-youtube',
            'paypal'      => 'bi-paypal',
            'steam'       => 'bi-steam',
            'reddit'      => 'bi-reddit',
            'slack'       => 'bi-slack',
            'dropbox'     => 'bi-dropbox',
            'docker'      => 'bi-docker',
            'nintendo'    => 'bi-nintendo-switch',
            'spotify'     => 'bi-spotify',
            'linkedin'    => 'bi-linkedin',
            'wordpress'   => 'bi-wordpress',
            'whatsapp'    => 'bi-whatsapp',
            'telegram'    => 'bi-telegram',
            'signal'      => 'bi-signal',
            'skype'       => 'bi-skype',
            'stripe'      => 'bi-stripe',
            'linux'       => 'bi-linux',
            'android'     => 'bi-android2',
            'snapchat'    => 'bi-snapchat',
            'pinterest'   => 'bi-pinterest',
            'cloudflare'  => 'bi-cloud-fill',
            'proton'      => 'bi-envelope-fill',
            'bitwarden'   => 'bi-shield-lock-fill',
            'npm'         => 'bi-npm',
        ];
        foreach ($map as $keyword => $icon) {
            if (str_contains($k, $keyword)) return $icon;
        }
        return null;
    }

    function otpIssuerColor(string $source, array $colors): string {
        if (empty($colors)) return '#5865f2';
        $hash = 0;
        for ($i = 0, $len = strlen($source); $i < $len; $i++) {
            $hash = (int)(($hash * 31 + ord($source[$i])) & 0x7FFFFFFF);
        }
        return $colors[$hash % count($colors)];
    }
}
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

<!-- Collection filter bar -->
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
            <?php if ($personalEnabled): ?>
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

<div class="row g-2 mb-3">
    <div class="col-md-8">
        <label for="otp-search" class="visually-hidden">Rechercher un code OTP</label>
        <input type="text" id="otp-search" class="form-control" placeholder="Rechercher un code OTP..."
               value="<?= htmlspecialchars($search ?? '') ?>" autofocus>
    </div>
    <div class="col-md-4">
        <label for="otp-issuer-filter" class="visually-hidden">Filtrer par émetteur</label>
        <select id="otp-issuer-filter" class="form-select">
            <option value="">Tous les émetteurs</option>
        </select>
    </div>
</div>

<!-- Codes personnels -->
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
                $avatarIcon   = otpIssuerIcon((string)($code['issuer'] ?? ''));
                $avatarLetter = strtoupper(mb_substr($code['issuer'] ?: $code['name'], 0, 1));
                $avatarColor  = $avatarIcon ? '' : otpIssuerColor($code['issuer'] ?: $code['name'], $iconColors);
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
                                            data-period="<?= htmlspecialchars((string)($code['period'] ?? 30)) ?>"
                                            data-can-delete="1">
                                        <i class="bi bi-pencil me-2"></i>Modifier
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="/otp/export?ids=<?= htmlspecialchars($code['id']) ?>">
                                        <i class="bi bi-qr-code me-2"></i>Exporter QR
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="account-avatar <?= $avatarIcon ? 'av-icon' : 'av-letter' ?>"
                         <?= $avatarColor ? 'style="--av-color:' . htmlspecialchars($avatarColor) . '"' : '' ?>>
                        <?php if ($avatarIcon): ?>
                            <i class="bi <?= $avatarIcon ?>"></i>
                        <?php else: ?>
                            <span><?= htmlspecialchars($avatarLetter) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="account-name"><?= htmlspecialchars($code['name']) ?></div>
                    <div class="account-issuer"><?= htmlspecialchars($code['issuer'] ?? '') ?>&nbsp;</div>
                    <div class="otp-code-row">
                        <div class="countdown-ring" data-timer-id="<?= htmlspecialchars($code['id']) ?>">
                            <svg viewBox="0 0 36 36">
                                <circle class="ring-bg" cx="18" cy="18" r="15.5"/>
                                <circle class="ring-fg" cx="18" cy="18" r="15.5"
                                        stroke-dasharray="97.39" stroke-dashoffset="0"/>
                            </svg>
                            <span class="ring-text">--</span>
                        </div>
                        <div class="otp-value" data-otp-id="<?= htmlspecialchars($code['id']) ?>">
                            <span class="code-display">···  ···</span>
                        </div>
                    </div>
                    <div class="copy-toast"><i class="bi bi-check-lg me-1"></i>Copié</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Collection codes -->
    <?php if ($showTenantCodes): ?>
    <?php if (!empty($currentTenantId)): ?>
    <div class="otp-layout">
        <nav class="folder-sidebar">
            <a href="/otp" class="folder-sidebar-item <?= $currentFolderId === '' ? 'active' : '' ?>">
                <i class="bi bi-house-fill"></i>
                <span class="folder-sidebar-name">Racine</span>
                <span class="folder-sidebar-badge"><?= count($rootTenantCodes) ?></span>
            </a>
            <?php foreach ($tenantFolders as $folder): ?>
            <div class="folder-sidebar-row">
                <a href="/otp?folder=<?= htmlspecialchars((string)($folder['id'] ?? '')) ?>"
                   class="folder-sidebar-item <?= $currentFolderId === (string)($folder['id'] ?? '') ? 'active' : '' ?>">
                    <i class="bi bi-folder-fill"></i>
                    <span class="folder-sidebar-name"><?= htmlspecialchars((string)($folder['name'] ?? 'Dossier')) ?></span>
                    <span class="folder-sidebar-badge"><?= (int)($folder['code_count'] ?? 0) ?></span>
                </a>
                <?php if ($currentTenantCanManageOtp): ?>
                <button type="button" class="folder-sidebar-edit"
                        title="Modifier le dossier"
                        aria-label="Modifier le dossier"
                        data-folder-id="<?= htmlspecialchars((string)($folder['id'] ?? '')) ?>"
                        data-folder-name="<?= htmlspecialchars((string)($folder['name'] ?? '')) ?>"
                        onclick="openEditGroupModal(this)">
                    <i class="bi bi-pencil"></i>
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($currentTenantCanManageOtp): ?>
            <button class="folder-sidebar-new" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="bi bi-folder-plus"></i>
                <span>Nouveau dossier</span>
            </button>
            <?php endif; ?>
        </nav>
        <div class="otp-content">
    <?php endif; ?>
    <?php if (!empty($tenantCodes)): ?>
    <div class="section-label <?= !empty($currentTenantId) ? 'mb-3' : 'mt-4' ?>">
        <i class="bi <?= !empty($currentTenantId) ? ($currentFolderId !== '' ? 'bi-folder2-open' : 'bi-house') : 'bi-building' ?>"></i>
        <?php if (!empty($currentTenantId) && $currentFolderId !== ''): ?>
            <?= htmlspecialchars($currentTenantName) ?> / <?= htmlspecialchars($currentFolderName) ?>
        <?php elseif (!empty($currentTenantId)): ?>
            <?= htmlspecialchars($currentTenantName) ?> / Racine
        <?php else: ?>
            <?= htmlspecialchars($currentTenantName) ?>
        <?php endif; ?>
        <span class="badge bg-success"><?= count($tenantCodes) ?></span>
    </div>

    <div class="otp-grid mb-4" id="tenant-codes">
        <?php foreach ($tenantCodes as $i => $code):
            $avatarIcon   = otpIssuerIcon((string)($code['issuer'] ?? ''));
            $avatarLetter = strtoupper(mb_substr($code['issuer'] ?: $code['name'], 0, 1));
            $avatarColor  = $avatarIcon ? '' : otpIssuerColor($code['issuer'] ?: $code['name'], $iconColors);
            $tenantId = (string)($code['tenant'] ?? '');
            $groupName = (string)($code['expand']['group']['name'] ?? '');
            $canManageThisTenant = !empty($tenantManageOtpMap[$tenantId]);
            $isCodeOwner = (string)($code['owner'] ?? '') === (string)($currentUser['id'] ?? '');
            $canManageTenantCode = !empty($currentUser['is_app_admin']) || $canManageThisTenant || $isCodeOwner;
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
                            <?php if ($canManageTenantCode): ?>
                            <li>
                                <button type="button" class="dropdown-item btn-edit-otp"
                                        data-id="<?= htmlspecialchars($code['id']) ?>"
                                        data-name="<?= htmlspecialchars($code['name']) ?>"
                                        data-issuer="<?= htmlspecialchars($code['issuer'] ?? '') ?>"
                                        data-secret="<?= htmlspecialchars($code['secret'] ?? '') ?>"
                                        data-algorithm="<?= htmlspecialchars($code['algorithm'] ?? 'SHA1') ?>"
                                        data-digits="<?= htmlspecialchars((string)($code['digits'] ?? 6)) ?>"
                                        data-period="<?= htmlspecialchars((string)($code['period'] ?? 30)) ?>"
                                        data-group="<?= htmlspecialchars((string)($code['group'] ?? '')) ?>"
                                        data-can-delete="1">
                                    <i class="bi bi-pencil me-2"></i>Modifier
                                </button>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="/otp/export?ids=<?= htmlspecialchars($code['id']) ?>">
                                    <i class="bi bi-qr-code me-2"></i>Exporter QR
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="account-avatar <?= $avatarIcon ? 'av-icon' : 'av-letter' ?>"
                     <?= $avatarColor ? 'style="--av-color:' . htmlspecialchars($avatarColor) . '"' : '' ?>>
                    <?php if ($avatarIcon): ?>
                        <i class="bi <?= $avatarIcon ?>"></i>
                    <?php else: ?>
                        <span><?= htmlspecialchars($avatarLetter) ?></span>
                    <?php endif; ?>
                </div>

                <div class="account-name"><?= htmlspecialchars($code['name']) ?></div>
                <div class="account-issuer"><?= htmlspecialchars($code['issuer'] ?? '') ?>&nbsp;</div>
                <?php if ($groupName !== ''): ?>
                    <div class="account-group"><i class="bi bi-folder me-1"></i><?= htmlspecialchars($groupName) ?></div>
                <?php endif; ?>
                <div class="otp-code-row">
                    <div class="countdown-ring" data-timer-id="<?= htmlspecialchars($code['id']) ?>">
                        <svg viewBox="0 0 36 36">
                            <circle class="ring-bg" cx="18" cy="18" r="15.5"/>
                            <circle class="ring-fg" cx="18" cy="18" r="15.5"
                                    stroke-dasharray="97.39" stroke-dashoffset="0"/>
                        </svg>
                        <span class="ring-text">--</span>
                    </div>
                    <div class="otp-value" data-otp-id="<?= htmlspecialchars($code['id']) ?>">
                        <span class="code-display">···  ···</span>
                    </div>
                </div>
                <div class="copy-toast"><i class="bi bi-check-lg me-1"></i>Copié</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php elseif (!empty($currentTenantId)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-key"></i></div>
        <p><?= $currentFolderId !== '' ? 'Aucun code OTP dans ce dossier.' : 'Aucun code OTP à la racine de cette collection.' ?></p>
    </div>
    <?php endif; ?>
    <?php if (!empty($currentTenantId)): ?>
        </div><!-- .otp-content -->
    </div><!-- .otp-layout -->
    <?php endif; ?>
    <?php endif; ?>


<?php if ($canCreateOtp): ?>
<?php if (!empty($currentTenantId) && $currentTenantCanManageOtp): ?>
<!-- Modale de modification de dossier -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-folder2 me-2"></i>Modifier le dossier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="/otp/groups/rename" id="editGroupRenameForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="group_id" id="editGroupId">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
                    <div class="mb-0">
                        <label class="form-label">Nom du dossier *</label>
                        <input type="text" name="name" id="editGroupName" class="form-control" maxlength="120" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/otp/groups/delete" id="editGroupDeleteForm" class="me-auto"
                      onsubmit="return confirm('Supprimer ce dossier ? Les codes seront déplacés à la racine.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="group_id" id="editGroupDeleteId">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="editGroupRenameForm" class="btn btn-accent">Enregistrer</button>
            </div>
        </div>
    </div>
</div>
<script>
function openEditGroupModal(btn) {
    var id   = btn.dataset.folderId   || '';
    var name = btn.dataset.folderName || '';
    document.getElementById('editGroupId').value       = id;
    document.getElementById('editGroupName').value     = name;
    document.getElementById('editGroupDeleteId').value = id;
    var modal = new bootstrap.Modal(document.getElementById('editGroupModal'));
    modal.show();
}
</script>
<div class="modal fade" id="createGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/groups/create">
            <?= csrfField() ?>
            <input type="hidden" name="tenant_id" value="<?= htmlspecialchars((string)$currentTenantId) ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Nouveau dossier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-0">
                        <label class="form-label">Nom du dossier *</label>
                        <input type="text" name="name" class="form-control" maxlength="120" required placeholder="Ex: Infra, Clients, Build">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent">Créer</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modale d'import URI -->
<div class="modal fade" id="importUriModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/import" id="importUriForm">
            <?= csrfField() ?>
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
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
                    <?php if ($personalEnabled): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal_is_personal" name="is_personal" value="1"
                                   onchange="var ts=document.getElementById('modal-tenant-select'); var sel=ts.querySelector('select'); if(this.checked){ts.style.display='none'; sel.required=false;}else{ts.style.display='block'; sel.required=true;}">
                            <label class="form-check-label" for="modal_is_personal">Code personnel</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($currentTenantId)): ?>
                        <input type="hidden" name="tenant" value="<?= htmlspecialchars((string)$currentTenantId) ?>">
                        <div class="mb-3">
                            <label class="form-label">Collection cible</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentTenantName) ?>" readonly>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Dossier</label>
                            <select name="group_id" class="form-select">
                                <option value="">-- Racine --</option>
                                <?php foreach ($tenantGroups as $group): ?>
                                    <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= $currentFolderId === (string)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['name'] ?? 'Dossier') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="mb-0" id="modal-tenant-select"<?php if (count($otpWritableTenants) === 1): ?> style="display:none"<?php endif; ?>>
                            <label class="form-label">Collection cible *</label>
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
                                <small class="text-muted">Aucune collection disponible en écriture OTP pour votre rôle.</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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

    // Réinitialiser la modale à la fermeture
    document.getElementById('importUriModal')?.addEventListener('hidden.bs.modal', function() {
        if (uriInput) uriInput.value = '';
        preview.classList.add('d-none');
        importBtn.disabled = true;
        importBtn.innerHTML = '<i class="bi bi-download me-1"></i>Importer';
    });
})();
</script>

<!-- Modale d'ajout OTP -->
<div class="modal fade" id="addOtpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/add">
            <?= csrfField() ?>
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
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
                    <?php if ($personalEnabled): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_personal" name="is_personal"
                                   value="1" onchange="var ts=document.getElementById('tenant-select-add'); var sel=ts.querySelector('select'); if(this.checked){ts.style.display='none'; sel.required=false;}else{ts.style.display='block'; sel.required=true;}">
                            <label class="form-check-label" for="is_personal">Code personnel</label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($currentTenantId)): ?>
                        <input type="hidden" name="tenant" value="<?= htmlspecialchars((string)$currentTenantId) ?>">
                        <div class="mb-3">
                            <label class="form-label">Collection</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentTenantName) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dossier</label>
                            <select name="group_id" class="form-select">
                                <option value="">-- Racine --</option>
                                <?php foreach ($tenantGroups as $group): ?>
                                    <option value="<?= htmlspecialchars((string)$group['id']) ?>" <?= $currentFolderId === (string)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars($group['name'] ?? 'Dossier') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="mb-3" id="tenant-select-add">
                            <label class="form-label">Collection *</label>
                            <select name="tenant" class="form-select" required>
                                <option value="">-- Choisir une collection --</option>
                                <?php foreach ($otpWritableTenants as $t): ?>
                                    <option value="<?= htmlspecialchars($t['id']) ?>">
                                        <?= htmlspecialchars($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($otpWritableTenants)): ?>
                                <small class="text-muted">Aucune collection avec permission d'ecriture OTP pour votre role.</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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

<!-- Modale de modification OTP -->
<div class="modal fade" id="editOtpModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="/otp/edit" id="editOtpForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="edit-otp-id">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
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
                    <?php if (!empty($currentTenantId)): ?>
                    <div class="mb-0">
                        <label class="form-label">Dossier</label>
                        <select name="group_id" id="edit-otp-group" class="form-select">
                            <option value="">-- Racine --</option>
                            <?php foreach ($tenantGroups as $group): ?>
                                <option value="<?= htmlspecialchars((string)$group['id']) ?>"><?= htmlspecialchars($group['name'] ?? 'Dossier') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="edit-otp-delete-btn"
                            onclick="(function(){var src=document.getElementById('edit-otp-id');var dst=document.getElementById('edit-delete-otp-id');var form=document.getElementById('deleteOtpForm');if(dst&&src){dst.value=src.value||'';}if(!dst||!dst.value){alert('Impossible de supprimer: identifiant OTP manquant.');return;}if(form){form.requestSubmit();}})();">
                        <i class="bi bi-trash me-1"></i>Supprimer
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent" form="editOtpForm">Enregistrer</button>
                </div>
            </div>
        </form>
        <form method="POST" action="/otp/delete" id="deleteOtpForm"
              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce code OTP ?');">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="edit-delete-otp-id">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($otpReturnPath) ?>">
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
