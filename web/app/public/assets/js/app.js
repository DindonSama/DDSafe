/**
 * 2FA Manager — Client-side JavaScript (2FAuth-inspired)
 */

const RING_CIRCUMFERENCE = 97.39; // 2 * PI * 15.5

/** Copy an OTP code from a card — called via onclick on .otp-account */
function copyOtpCode(card, id) {
    const codeEl = card.querySelector('.code-display');
    if (!codeEl) return;
    const code = codeEl.textContent.replace(/[^0-9]/g, '');
    if (!code || code === '') return;

    function showCopied() {
        card.classList.add('copied');
        setTimeout(() => card.classList.remove('copied'), 1200);

        // Clear the search input after a successful copy and show all cards again.
        const searchInput = document.getElementById('otp-search');
        if (searchInput && searchInput.value !== '') {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(showCopied);
    } else {
        // Fallback for non-HTTPS contexts
        const ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showCopied();
    }
}

function handleOtpCardKey(event, card, id) {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        copyOtpCode(card, id);
    }
}

/** Copy the next OTP code when clicking on .next-code-display */
function copyNextOtpCode(event, el) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (!el) return;

    const code = (el.textContent || '').replace(/[^0-9]/g, '');
    if (!code) return;

    function showCopiedFeedback() {
        const card = el.closest('.otp-account');
        if (card) {
            card.classList.add('copied');
            setTimeout(() => card.classList.remove('copied'), 1200);
        }
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(showCopiedFeedback);
    } else {
        const ta = document.createElement('textarea');
        ta.value = code;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showCopiedFeedback();
    }
}

document.addEventListener('DOMContentLoaded', function () {

    // ── OTP Code Refresh (circular ring) ─────────────────────────
    const otpElements = document.querySelectorAll('[data-otp-id]');
    if (otpElements.length > 0) {
        const otpIds = Array.from(new Set(
            Array.from(otpElements)
                .map(el => el.dataset.otpId)
                .filter(Boolean)
        ));

        function refreshCodes() {
            if (otpIds.length === 0) return;

            fetch('/api/otp/codes?ids=' + otpIds.join(','))
                .then(r => r.json())
                .then(data => {
                    const codes = data.codes || {};
                    for (const [id, info] of Object.entries(codes)) {
                        // Update code displays
                        document.querySelectorAll(`[data-otp-id="${id}"] .code-display`).forEach(el => {
                            el.textContent = formatCode(info.code);
                        });
                        document.querySelectorAll(`[data-otp-id="${id}"] .next-code-display`).forEach(el => {
                            el.textContent = formatCode(info.next_code || '');
                        });

                        // Update countdown rings
                        const period = info.period || 30;
                        const remaining = info.remaining || 0;
                        const pct = remaining / period;
                        const offset = RING_CIRCUMFERENCE * (1 - pct);

                        document.querySelectorAll(`[data-timer-id="${id}"]`).forEach(ring => {
                            const fg = ring.querySelector('.ring-fg');
                            const text = ring.querySelector('.ring-text');
                            if (fg) {
                                fg.style.strokeDashoffset = offset;
                                // Color based on remaining time
                                if (remaining <= 5) {
                                    fg.style.stroke = 'var(--ring-danger)';
                                } else if (remaining <= 10) {
                                    fg.style.stroke = 'var(--ring-warning)';
                                } else {
                                    fg.style.stroke = 'var(--ring-fg)';
                                }
                            }
                            if (text) {
                                text.textContent = remaining;
                            }
                        });
                    }
                })
                .catch(err => console.warn('OTP refresh error:', err));
        }

        refreshCodes();
        setInterval(refreshCodes, 1000);
    }

    function formatCode(code) {
        if (!code) return '···  ···';
        const mid = Math.ceil(code.length / 2);
        return code.slice(0, mid) + ' ' + code.slice(mid);
    }

    // ── Edit OTP Modal ──────────────────────────────────────────
    const editOtpSecretInput = document.getElementById('edit-otp-secret');
    const toggleEditOtpSecretBtn = document.getElementById('toggle-edit-otp-secret');
    const editOtpModalEl = document.getElementById('editOtpModal');
    const editOtpDeleteBtn = document.getElementById('edit-otp-delete-btn');
    const editDeleteOtpIdInput = document.getElementById('edit-delete-otp-id');
    const editOtpIdInput = document.getElementById('edit-otp-id');
    const editOtpNameInput = document.getElementById('edit-otp-name');
    const editOtpGroupInput = document.getElementById('edit-otp-group');
    const deleteOtpForm = document.getElementById('deleteOtpForm');
    const deleteOtpConfirmModalEl = document.getElementById('deleteOtpConfirmModal');
    const confirmDeleteOtpBtn = document.getElementById('confirm-delete-otp-btn');
    const deleteOtpNameEl = document.getElementById('delete-otp-name');
    const deleteOtpScopeLabelEl = document.getElementById('delete-otp-scope-label');
    const deleteOtpDescriptionEl = document.getElementById('delete-otp-description');
    const deleteOtpIconWrapEl = document.getElementById('delete-otp-icon-wrap');
    const deleteOtpIconEl = document.getElementById('delete-otp-icon');
    const selectAllOtpBtn = document.getElementById('select-all-otp-btn');
    const clearAllOtpBtn = document.getElementById('clear-all-otp-btn');
    const exportSelectedUriBtn = document.getElementById('export-selected-uri-btn');
    const selectionActionBar = document.getElementById('selection-action-bar');
    const selectedOtpCountEl = document.getElementById('selected-otp-count');

    function setSecretVisibility(visible) {
        if (!editOtpSecretInput || !toggleEditOtpSecretBtn) return;
        editOtpSecretInput.type = visible ? 'text' : 'password';
        toggleEditOtpSecretBtn.setAttribute('aria-pressed', visible ? 'true' : 'false');
        toggleEditOtpSecretBtn.setAttribute('aria-label', visible ? 'Masquer le secret' : 'Afficher le secret');
        toggleEditOtpSecretBtn.innerHTML = visible
            ? '<i class="bi bi-eye-slash me-1"></i>Masquer'
            : '<i class="bi bi-eye me-1"></i>Afficher';
    }

    if (toggleEditOtpSecretBtn) {
        toggleEditOtpSecretBtn.addEventListener('click', function () {
            const isVisible = editOtpSecretInput && editOtpSecretInput.type === 'text';
            setSecretVisibility(!isVisible);
        });
    }

    if (editOtpModalEl) {
        editOtpModalEl.addEventListener('hidden.bs.modal', function () {
            setSecretVisibility(false);
            if (editDeleteOtpIdInput) {
                editDeleteOtpIdInput.value = '';
            }
            if (editOtpGroupInput) {
                editOtpGroupInput.value = '';
            }
        });
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-otp');
        if (!btn) return;

        document.getElementById('edit-otp-id').value = btn.dataset.id;
        document.getElementById('edit-otp-name').value = btn.dataset.name;
        document.getElementById('edit-otp-issuer').value = btn.dataset.issuer || '';
        document.getElementById('edit-otp-secret').value = btn.dataset.secret || '';
        document.getElementById('edit-otp-algorithm').value = (btn.dataset.algorithm || 'SHA1').toUpperCase();
        document.getElementById('edit-otp-digits').value = String(btn.dataset.digits || '6');
        document.getElementById('edit-otp-period').value = String(btn.dataset.period || '30');
        if (deleteOtpForm) {
            deleteOtpForm.dataset.scope = btn.dataset.deleteScope || 'tenant';
        }
        if (editOtpDeleteBtn) {
            const canDelete = (btn.dataset.canDelete || '0') === '1';
            editOtpDeleteBtn.classList.toggle('d-none', !canDelete);
        }
        if (editOtpGroupInput) {
            editOtpGroupInput.value = btn.dataset.group || '';
        }
        if (editDeleteOtpIdInput) {
            editDeleteOtpIdInput.value = btn.dataset.id || '';
        }
        setSecretVisibility(false);

        const modal = new bootstrap.Modal(document.getElementById('editOtpModal'));
        modal.show();
    }, true);

    if (editOtpDeleteBtn && deleteOtpForm) {
        editOtpDeleteBtn.addEventListener('click', function () {
            // Keep delete id in sync with the edited OTP even if hidden field got reset.
            if (editDeleteOtpIdInput && !editDeleteOtpIdInput.value && editOtpIdInput) {
                editDeleteOtpIdInput.value = editOtpIdInput.value || '';
            }
            if (!editDeleteOtpIdInput || !editDeleteOtpIdInput.value) {
                alert('Impossible de supprimer: identifiant OTP manquant.');
                return;
            }

            if (deleteOtpNameEl) {
                const otpName = (editOtpNameInput?.value || '').trim();
                deleteOtpNameEl.textContent = otpName !== '' ? otpName : 'ce code OTP';
            }

            const deleteScope = deleteOtpForm?.dataset?.scope || 'tenant';
            if (deleteOtpScopeLabelEl) {
                deleteOtpScopeLabelEl.textContent = deleteScope === 'personal' ? 'Code personnel' : 'Code de collection';
            }
            if (deleteOtpDescriptionEl) {
                deleteOtpDescriptionEl.textContent = deleteScope === 'personal'
                    ? 'Ce code personnel sera masque puis pourra etre restaure depuis l\'administration.'
                    : 'Ce code de collection sera masque puis pourra etre restaure depuis l\'administration.';
            }
            if (deleteOtpIconWrapEl) {
                deleteOtpIconWrapEl.style.background = deleteScope === 'personal'
                    ? 'rgba(13, 110, 253, .12)'
                    : 'rgba(220, 53, 69, .12)';
                deleteOtpIconWrapEl.style.color = deleteScope === 'personal' ? '#0d6efd' : '#dc3545';
            }
            if (deleteOtpIconEl) {
                deleteOtpIconEl.className = deleteScope === 'personal'
                    ? 'bi bi-person-fill-lock'
                    : 'bi bi-collection-fill';
            }

            if (deleteOtpConfirmModalEl) {
                bootstrap.Modal.getOrCreateInstance(deleteOtpConfirmModalEl).show();
                return;
            }

            deleteOtpForm.requestSubmit();
        });
    }

    if (confirmDeleteOtpBtn && deleteOtpForm) {
        confirmDeleteOtpBtn.addEventListener('click', function () {
            deleteOtpForm.requestSubmit();
        });
    }

    if (exportSelectedUriBtn) {
        const updateSelectedExportButton = function () {
            const checked = document.querySelectorAll('.otp-export-select:checked').length;
            exportSelectedUriBtn.disabled = checked === 0;
            if (clearAllOtpBtn) {
                clearAllOtpBtn.disabled = checked === 0;
            }
            exportSelectedUriBtn.innerHTML = '<i class="bi bi-link-45deg me-1"></i>Exporter URI (' + checked + ')';
            if (selectionActionBar) {
                selectionActionBar.classList.toggle('d-none', checked === 0);
            }
            if (selectedOtpCountEl) {
                selectedOtpCountEl.textContent = String(checked);
            }
        };

        if (selectAllOtpBtn) {
            selectAllOtpBtn.addEventListener('click', function () {
                document.querySelectorAll('.otp-export-select').forEach(input => {
                    const item = input.closest('.otp-item');
                    if (item && item.style.display === 'none') {
                        return;
                    }
                    input.checked = true;
                });
                updateSelectedExportButton();
            });
        }

        if (clearAllOtpBtn) {
            clearAllOtpBtn.addEventListener('click', function () {
                document.querySelectorAll('.otp-export-select:checked').forEach(input => {
                    input.checked = false;
                });
                updateSelectedExportButton();
            });
        }

        document.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('otp-export-select')) {
                updateSelectedExportButton();
            }
        });

        exportSelectedUriBtn.addEventListener('click', function () {
            const ids = [];
            document.querySelectorAll('.otp-export-select:checked').forEach(input => {
                const id = input.dataset?.otpId || '';
                if (id && !ids.includes(id)) {
                    ids.push(id);
                }
            });

            if (ids.length === 0) {
                alert('Veuillez sélectionner au moins un code OTP à exporter.');
                updateSelectedExportButton();
                return;
            }

            window.location.href = '/otp/export?ids=' + encodeURIComponent(ids.join(','));
        });

        updateSelectedExportButton();
    }

    // ── Edit User Modal ─────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-user');
        if (!btn) return;

        document.getElementById('edit-user-id').value = btn.dataset.id;
        document.getElementById('edit-user-name').value = btn.dataset.name || '';
        document.getElementById('edit-user-email').value = btn.dataset.email || '';

        const pwdInput = document.getElementById('edit-user-password');
        const pwdHelp = document.getElementById('edit-user-password-help');
        const isFederated = (btn.dataset.isAdUser === '1') || (btn.dataset.isOidcUser === '1');
        if (pwdInput) {
            pwdInput.value = '';
            pwdInput.disabled = isFederated;
            pwdInput.placeholder = isFederated
                ? 'Compte AD/OIDC: mot de passe géré par l\'identité externe'
                : 'Laisser vide pour ne pas changer';
        }
        if (pwdHelp) {
            pwdHelp.textContent = isFederated
                ? 'Compte AD/OIDC: le mot de passe ne peut pas etre modifie ici.'
                : 'Laisser vide pour ne pas changer.';
        }

        const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    });

    // ── Live Search + Issuer Filter (client-side filtering) ─────
    const otpSearch = document.getElementById('otp-search');
    const otpIssuerFilter = document.getElementById('otp-issuer-filter');
    const otpFavoritesOnlyBtn = document.getElementById('otp-favorites-only-btn');
    let favoritesOnly = false;

    function applyOtpFilters() {
        const q = (otpSearch?.value || '').toLowerCase().trim();
        const issuerFilter = (otpIssuerFilter?.value || '').toLowerCase().trim();

        document.querySelectorAll('.otp-item').forEach(item => {
            const name = (item.dataset.name || '').toLowerCase();
            const issuer = (item.dataset.issuer || '').toLowerCase();
            const isFavorite = (item.dataset.favorite || '0') === '1';
            const matchSearch = q === '' || name.includes(q) || issuer.includes(q);
            const matchIssuer = issuerFilter === '' || issuer === issuerFilter;
            const matchFavorite = !favoritesOnly || isFavorite;
            item.style.display = matchSearch && matchIssuer && matchFavorite ? '' : 'none';
        });
    }

    if (otpFavoritesOnlyBtn) {
        otpFavoritesOnlyBtn.addEventListener('click', function () {
            favoritesOnly = !favoritesOnly;
            otpFavoritesOnlyBtn.setAttribute('aria-pressed', favoritesOnly ? 'true' : 'false');
            otpFavoritesOnlyBtn.classList.toggle('btn-warning', favoritesOnly);
            otpFavoritesOnlyBtn.classList.toggle('btn-outline-warning', !favoritesOnly);
            applyOtpFilters();
        });
    }

    if (otpIssuerFilter) {
        const formatIssuerLabel = (issuer) => {
            const cleaned = (issuer || '').trim();
            if (cleaned === '') return '';
            return cleaned.charAt(0).toLocaleUpperCase('fr-FR') + cleaned.slice(1);
        };

        const issuers = new Set();
        document.querySelectorAll('.otp-item').forEach(item => {
            const rawIssuer = (item.dataset.issuer || '').trim();
            if (rawIssuer !== '') {
                issuers.add(rawIssuer);
            }
        });

        Array.from(issuers)
            .sort((a, b) => a.localeCompare(b, 'fr', { sensitivity: 'base' }))
            .forEach(issuer => {
                const option = document.createElement('option');
                option.value = issuer.toLowerCase();
                option.textContent = formatIssuerLabel(issuer);
                otpIssuerFilter.appendChild(option);
            });

        otpIssuerFilter.addEventListener('change', applyOtpFilters);
    }

    if (otpSearch) {
        let otpSearchWasBlurred = false;

        otpSearch.addEventListener('input', applyOtpFilters);
        otpSearch.addEventListener('blur', function () {
            otpSearchWasBlurred = true;
        });
        otpSearch.addEventListener('focus', function () {
            if (!otpSearchWasBlurred) {
                return;
            }
            if (otpSearch.value !== '') {
                otpSearch.value = '';
                otpSearch.dispatchEvent(new Event('input', { bubbles: true }));
            }
            otpSearchWasBlurred = false;
        });

        // Quick search UX: typing on empty page space writes directly into the search input.
        document.addEventListener('keydown', function (event) {
            const active = document.activeElement;
            const activeTag = (active?.tagName || '').toLowerCase();
            const isEditable = !!active && (
                activeTag === 'input' ||
                activeTag === 'textarea' ||
                activeTag === 'select' ||
                active.isContentEditable
            );

            if (isEditable) {
                return;
            }

            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            if (event.key.length === 1 && !event.key.match(/\s/)) {
                event.preventDefault();
                otpSearch.focus();
                otpSearch.value = (otpSearch.value || '') + event.key;
                otpSearch.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }

            if (event.key === 'Backspace' && (otpSearch.value || '') !== '') {
                event.preventDefault();
                otpSearch.focus();
                otpSearch.value = otpSearch.value.slice(0, -1);
                otpSearch.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    const searchInputs = document.querySelectorAll('[data-live-search]');
    searchInputs.forEach(input => {
        input.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            const target = document.getElementById(this.dataset.liveSearch);
            if (!target) return;

            target.querySelectorAll('.otp-item').forEach(item => {
                const name = item.dataset.name || '';
                const issuer = item.dataset.issuer || '';
                const match = q === '' || name.includes(q) || issuer.includes(q);
                item.style.display = match ? '' : 'none';
            });
        });
    });

    // ── Sidebar toggle accessibility ─────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                sidebarToggle.setAttribute('aria-expanded', 'false');
                sidebarToggle.focus();
            }
        });
    }

    // ── Sidebar mobile close on link click ──────────────────────
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebar-toggle');
            if (sidebar) sidebar.classList.remove('open');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    });
});
