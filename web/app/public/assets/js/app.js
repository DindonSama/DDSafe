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

document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
        setSecretVisibility(false);

        const modal = new bootstrap.Modal(document.getElementById('editOtpModal'));
        modal.show();
    }, true);

    // ── Delete OTP Modal ────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-delete-otp');
        if (!btn) return;

        document.getElementById('delete-otp-id').value = btn.dataset.id;
        document.getElementById('delete-otp-name').textContent = btn.dataset.name;

        const modal = new bootstrap.Modal(document.getElementById('deleteOtpModal'));
        modal.show();
    }, true);

    // ── Edit User Modal ─────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-user');
        if (!btn) return;

        document.getElementById('edit-user-id').value = btn.dataset.id;
        document.getElementById('edit-user-name').value = btn.dataset.name || '';
        document.getElementById('edit-user-email').value = btn.dataset.email || '';

        const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    });

    // ── Live Search (client-side filtering) ─────────────────────
    const otpSearch = document.getElementById('otp-search');
    if (otpSearch) {
        otpSearch.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('.otp-item').forEach(item => {
                const name = item.dataset.name || '';
                const issuer = item.dataset.issuer || '';
                const match = q === '' || name.includes(q) || issuer.includes(q);
                item.style.display = match ? '' : 'none';
            });
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
