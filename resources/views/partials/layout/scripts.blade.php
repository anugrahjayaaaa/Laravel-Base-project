<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/adminlte/js/adminlte.min.js') }}"></script>
<script>
    (function () {
        const root = document.documentElement;
        const icon = document.getElementById('theme-icon');
        const saved = localStorage.getItem('theme') || 'dark';
        root.setAttribute('data-bs-theme', saved);
        icon.className = saved === 'dark' ? 'bi bi-moon-stars fs-5' : 'bi bi-sun fs-5';
        document.getElementById('theme-toggle').addEventListener('click', function () {
            const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            icon.className = next === 'dark' ? 'bi bi-moon-stars fs-5' : 'bi bi-sun fs-5';
        });
        // tooltips for action/icon buttons
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

        // Live header search -> dropdown of matching menu links (no sidebar filtering; click navigates)
        (function () {
            const input = document.getElementById('menu-search');
            const results = document.getElementById('menu-search-results');
            if (!input || !results) return;
            const items = [...document.querySelectorAll('.sidebar-nav a[data-menu-text]')]
                .map(a => ({ text: a.dataset.menuText, href: a.getAttribute('href') }));

            const render = (q) => {
                const matches = q === '' ? [] : items.filter(i => i.text.toLowerCase().includes(q));
                results.innerHTML = matches.length
                    ? matches.map(i => `<li><a class="dropdown-item py-2" href="${i.href}"><i class="bi bi-box-arrow-up-right me-2 opacity-50"></i>${i.text}</a></li>`).join('')
                    : `<li><span class="dropdown-item text-muted">{{ ui('no_menu_found') }}</span></li>`;
                results.style.display = 'block';
                input.setAttribute('aria-expanded', 'true');
            };
            const close = () => { results.style.display = 'none'; input.setAttribute('aria-expanded', 'false'); };

            input.addEventListener('input', () => render(input.value.trim().toLowerCase()));
            input.addEventListener('focus', () => { if (input.value.trim() !== '') render(input.value.trim().toLowerCase()); });
            input.addEventListener('keydown', e => { if (e.key === 'Escape') { input.value = ''; close(); } });
            document.addEventListener('click', e => { if (!e.target.closest('.position-relative')) close(); });
        })();

        // Single delegated handler for all confirmation modals (delete / force-delete / feature-toggle).
        // Trigger buttons supply data-action; feature-toggle also supplies data-enabled.
        document.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            if (!btn) return;
            const form = e.target.querySelector('form');
            const action = btn.getAttribute('data-action');
            if (form && action) form.setAttribute('action', action);

            const enabled = document.getElementById('featureToggleEnabled');
            if (enabled) {
                const next = btn.getAttribute('data-enabled') === '1' ? 'enable' : 'disable';
                enabled.value = btn.getAttribute('data-enabled');
                const body = document.getElementById('featureToggleBody');
                const submit = document.getElementById('featureToggleSubmit');
                if (body) body.textContent = next === 'enable' ? '{{ ui('confirm_enable_feature') }}' : '{{ ui('confirm_disable_feature') }}';
                if (submit) submit.textContent = next === 'enable' ? '{{ ui('enable') }}' : '{{ ui('disable') }}';
                // ponytail: remember the real current state so Cancel can restore the switch
                e.target._featureChk = btn;
            }
        });

        // Cancel/backdrop/Esc must restore the switch to its real state — the
        // checkbox already flipped when clicked, so the confirm modal owns restoring it.
        document.addEventListener('hide.bs.modal', function (e) {
            const chk = e.target._featureChk;
            if (!chk) return;
            chk.checked = chk.getAttribute('data-enabled') === '0'; // '0' => currently enabled
            e.target._featureChk = null;
        });

        // Lock/Unlock user modal: trigger supplies data-action + data-label.
        document.addEventListener('show.bs.modal', function (e) {
            if (e.target.id !== 'lockUserModal') return;
            const btn = e.relatedTarget;
            if (!btn) return;
            const form = e.target.querySelector('#lockUserModalForm');
            const action = btn.getAttribute('data-action');
            const label = btn.getAttribute('data-label') || '{{ ui('confirm') }}';
            const body = e.target.querySelector('#lockUserModalBody');
            const submit = e.target.querySelector('#lockUserModalSubmit');
            const title = e.target.querySelector('#lockUserModalTitle');
            if (form && action) form.setAttribute('action', action);
            if (body) body.textContent = label;
            if (submit) submit.textContent = label;
            if (title) title.textContent = label;
        });
    })();
</script>
