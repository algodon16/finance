@if(request()->routeIs('admin.reports.*') && session('reports.unlocked') !== true)
<div class="reports-gate-overlay" id="reportsGateOverlay" role="dialog" aria-modal="true" aria-labelledby="reportsGateTitle">
    <div class="reports-gate-card">
        <h3 id="reportsGateTitle">Secure Access</h3>
        <p class="reports-gate-sub">Financial Reporting and Compliance contains sensitive financial records. Please enter your password to continue.</p>

        <div class="reports-gate-error" id="reportsGateError" style="display:none;"></div>

        <form id="reportsGateForm" autocomplete="off">
            <div class="form-group" style="margin-bottom:16px;">
                <label for="reportsGatePassword">Password</label>
                <div class="reports-gate-passwrap">
                    <input type="password" name="password" id="reportsGatePassword" class="form-control" placeholder="Enter your password" required autocomplete="current-password" autofocus>
                    <button type="button" class="reports-gate-toggle" id="reportsGateToggle" aria-label="Show password">Show</button>
                </div>
            </div>
            <div class="reports-gate-actions">
                <button type="submit" class="btn btn-primary" id="reportsGateSubmit">Verify Password</button>
                <button type="button" class="btn btn-secondary" id="reportsGateCancel">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const overlay = document.getElementById('reportsGateOverlay');
    const form = document.getElementById('reportsGateForm');
    const input = document.getElementById('reportsGatePassword');
    const err = document.getElementById('reportsGateError');
    const submit = document.getElementById('reportsGateSubmit');
    const toggle = document.getElementById('reportsGateToggle');
    const cancel = document.getElementById('reportsGateCancel');
    if (!overlay || !form) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    toggle.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Hide' : 'Show';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        input.focus();
    });

    const fail = (msg) => {
        err.textContent = msg;
        err.style.display = 'block';
        input.value = '';
        input.focus();
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        err.style.display = 'none';
        submit.disabled = true;
        submit.textContent = 'Verifying...';
        try {
            const res = await fetch(@json(route('admin.reports.verify')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ password: input.value })
            });
            if (res.ok) {
                window.location.reload();
                return;
            }
            const data = await res.json().catch(() => ({}));
            fail(data.message || 'Incorrect password. Please try again.');
        } catch (ex) {
            fail('Unable to verify right now. Please try again.');
        } finally {
            submit.disabled = false;
            submit.textContent = 'Verify Password';
        }
    });

    cancel.addEventListener('click', () => {
        if (window.history.length > 1) window.history.back();
        else window.location.href = @json(route('admin.dashboard'));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cancel.click();
    });
})();
</script>
@endif
