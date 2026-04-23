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
    let refreshCodes = null;
    const otpElements = document.querySelectorAll('[data-otp-id]');
    if (otpElements.length > 0) {
        function getVisibleOtpIds() {
            const seen = new Set();
            document.querySelectorAll('[data-otp-id]').forEach(el => {
                const id = el.dataset.otpId;
                if (!id) return;
                // Walk up to find the nearest .otp-item ancestor or self
                const item = el.closest('.otp-item') || el;
                if (item.style.display === 'none') return;
                seen.add(id);
            });
            return Array.from(seen);
        }

        refreshCodes = function refreshCodes() {
            const ids = getVisibleOtpIds();
            if (ids.length === 0) return;

            fetch('/api/otp/codes?ids=' + ids.join(','))
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

        // Gestion du select de collection (déplacement)
        const tenantSelect = document.getElementById('edit-otp-tenant');
        const tenantWrap = document.getElementById('edit-otp-tenant-wrap');
        const isPersonal = (btn.dataset.deleteScope || '') === 'personal';
        if (tenantWrap) {
            tenantWrap.style.display = isPersonal ? 'none' : '';
        }
        if (tenantSelect && !isPersonal && btn.dataset.tenant) {
            tenantSelect.value = btn.dataset.tenant;
        }

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
    const OTP_PER_PAGE_KEY = 'otp_per_page';
    const OTP_PER_PAGE_OPTIONS = [25, 50, 100, 9999];
    let OTP_PAGE_SIZE = parseInt(localStorage.getItem(OTP_PER_PAGE_KEY) || '25', 10);
    if (!OTP_PER_PAGE_OPTIONS.includes(OTP_PAGE_SIZE)) OTP_PAGE_SIZE = 25;
    const otpCurrentPage = {};

    function setOtpPageSize(value) {
        if (!OTP_PER_PAGE_OPTIONS.includes(value)) {
            return;
        }
        OTP_PAGE_SIZE = value;
        localStorage.setItem(OTP_PER_PAGE_KEY, String(value));
        document.querySelectorAll('.otp-per-page-select').forEach(select => {
            select.value = String(value);
        });
        document.querySelectorAll('[data-otp-section]').forEach(sec => {
            otpCurrentPage[sec.dataset.otpSection] = 1;
        });
        applyOtpPagination();
        if (refreshCodes) refreshCodes();
    }

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
            item.dataset.filterMatch = (matchSearch && matchIssuer && matchFavorite) ? '1' : '0';
        });
        document.querySelectorAll('[data-otp-section]').forEach(sec => {
            otpCurrentPage[sec.dataset.otpSection] = 1;
        });
        applyOtpPagination();
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

    // ── OTP Pagination ───────────────────────────────────────────
    function applyOtpPagination() {
        document.querySelectorAll('[data-otp-section]').forEach(section => {
            const sectionId = section.dataset.otpSection;
            const page = otpCurrentPage[sectionId] || 1;
            const items = Array.from(section.querySelectorAll('.otp-item'));
            const matchingCount = items.reduce((count, item) => count + (item.dataset.filterMatch !== '0' ? 1 : 0), 0);
            const start = (page - 1) * OTP_PAGE_SIZE;
            const end = start + OTP_PAGE_SIZE;
            let visibleIndex = 0;
            items.forEach(item => {
                if (item.dataset.filterMatch === '0') {
                    item.style.display = 'none';
                } else {
                    item.style.display = (visibleIndex >= start && visibleIndex < end) ? '' : 'none';
                    visibleIndex++;
                }
            });
            section.dataset.otpReady = '1';
            renderOtpPaginationControl(sectionId, matchingCount, page);
        });
    }

    function renderOtpPaginationControl(sectionId, totalItems, currentPage) {
        const container = document.getElementById('pagination-' + sectionId);
        if (!container) return;
        const totalPages = OTP_PAGE_SIZE >= 9999 ? 1 : Math.ceil(totalItems / OTP_PAGE_SIZE);
        if (totalItems <= 0) { container.innerHTML = ''; return; }
        const maxPageButtons = 5;
        let left = Math.max(1, currentPage - Math.floor(maxPageButtons / 2));
        let right = left + maxPageButtons - 1;
        if (right > totalPages) {
            right = totalPages;
            left = Math.max(1, right - maxPageButtons + 1);
        }
        const from  = Math.min((currentPage - 1) * OTP_PAGE_SIZE + 1, totalItems);
        const to    = Math.min(currentPage * OTP_PAGE_SIZE, totalItems);

        let pages = '';
        for (let i = left; i <= right; i++) {
            pages += '<button class="otp-page-btn' + (i === currentPage ? ' active' : '') + '" data-section="' + sectionId + '" data-page="' + i + '">' + i + '</button>';
        }

        const pagerNumbers = totalPages <= 1
            ? ''
            : '<button class="otp-page-btn otp-page-arrow" data-section="' + sectionId + '" data-page="' + (currentPage - 1) + '" ' + (currentPage <= 1 ? 'disabled' : '') + ' aria-label="Précédent"><i class="bi bi-chevron-left"></i></button>' +
              '<div class="otp-page-numbers">' + pages + '</div>' +
              '<button class="otp-page-btn otp-page-arrow" data-section="' + sectionId + '" data-page="' + (currentPage + 1) + '" ' + (currentPage >= totalPages ? 'disabled' : '') + ' aria-label="Suivant"><i class="bi bi-chevron-right"></i></button>';

        container.innerHTML =
            '<div class="otp-pager">' +
                '<div class="otp-pager-main">' +
                    pagerNumbers +
                    '<span class="otp-page-info">' + from + '\u2013' + to + ' <span class="otp-page-info-sep">sur</span> ' + totalItems + '</span>' +
                '</div>' +
                '<label class="otp-per-page-control">' +
                    '<span>Par page</span>' +
                    '<select class="otp-per-page-select" aria-label="Codes affichés par page">' +
                        '<option value="25"' + (OTP_PAGE_SIZE === 25 ? ' selected' : '') + '>25</option>' +
                        '<option value="50"' + (OTP_PAGE_SIZE === 50 ? ' selected' : '') + '>50</option>' +
                        '<option value="100"' + (OTP_PAGE_SIZE === 100 ? ' selected' : '') + '>100</option>' +
                        '<option value="9999"' + (OTP_PAGE_SIZE === 9999 ? ' selected' : '') + '>Tout</option>' +
                    '</select>' +
                '</label>' +
            '</div>';

        container.onclick = function handlePagerClick(e) {
            const btn = e.target.closest('.otp-page-btn[data-page]');
            if (!btn || btn.disabled) return;
            const sec = btn.dataset.section;
            const p = parseInt(btn.dataset.page, 10);
            if (!isNaN(p) && p >= 1 && p <= totalPages) {
                otpCurrentPage[sec] = p;
                applyOtpPagination();
                if (refreshCodes) refreshCodes();
            }
        };

        container.onchange = function handlePerPageChange(e) {
            const select = e.target.closest('.otp-per-page-select');
            if (!select) return;
            setOtpPageSize(parseInt(select.value, 10));
        };
    }

    // Init : tous les items sont visibles au chargement
    document.querySelectorAll('.otp-item').forEach(item => { item.dataset.filterMatch = '1'; });
    applyOtpPagination();

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
