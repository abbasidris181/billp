document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));

    button.hidden = false;
    button.addEventListener('click', () => {
        const showPassword = input.type === 'password';

        input.type = showPassword ? 'text' : 'password';
        button.toggleAttribute('data-visible', showPassword);
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    });
});

const strength = document.querySelector('[data-password-strength]');

if (strength) {
    const password = document.getElementById('password');
    const label = strength.querySelector('.auth-strength-label');

    const updateStrength = () => {
        const value = password.value;
        const variety = [/[a-z]/, /[A-Z]/, /\d/, /[^\p{L}\p{N}\s]/u]
            .filter((pattern) => pattern.test(value)).length;

        let level = 'empty';
        let message = 'Password strength';

        if (value.length > 0 && value.length < 8) {
            [level, message] = ['weak', 'Weak'];
        } else if (value.length >= 12 && variety >= 3) {
            [level, message] = ['strong', 'Strong'];
        } else if (value.length >= 8) {
            [level, message] = ['fair', 'Fair'];
        }

        strength.dataset.strength = level;
        label.textContent = message;
    };

    password.addEventListener('input', updateStrength);
    window.addEventListener('pageshow', updateStrength);
    updateStrength();
}

const navigationToggle = document.querySelector('[data-nav-toggle]');

if (navigationToggle) {
    const sidebar = document.getElementById(navigationToggle.getAttribute('aria-controls'));
    const overlay = document.querySelector('[data-nav-close]');
    const workspace = document.querySelector('.portal-workspace');
    const mobile = window.matchMedia('(max-width: 820px)');

    const setNavigation = (open) => {
        sidebar.toggleAttribute('data-open', open);
        overlay.hidden = !open;
        workspace.inert = open;
        navigationToggle.setAttribute('aria-expanded', String(open));
    };

    navigationToggle.addEventListener('click', () => {
        setNavigation(true);
        sidebar.querySelector('a').focus();
    });

    const closeNavigation = () => {
        setNavigation(false);
        navigationToggle.focus();
    };

    overlay.addEventListener('click', closeNavigation);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.hasAttribute('data-open')) {
            closeNavigation();
        }
    });
    mobile.addEventListener('change', () => setNavigation(false));
}

document.querySelectorAll('[data-copy-key]').forEach((button) => {
    button.addEventListener('click', async () => {
        const input = document.getElementById(button.dataset.copyKey);
        const feedback = document.querySelector('[data-copy-feedback]');

        try {
            await navigator.clipboard.writeText(input.value);
            feedback.textContent = 'Key copied.';
        } catch {
            input.select();
            feedback.textContent = 'Key selected. Copy it using your keyboard.';
        }
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

const instantIpeForm = document.querySelector('[data-instant-ipe-form]');
if (instantIpeForm) {
    const sections = instantIpeForm.querySelectorAll('[data-ipe-mode]');
    const bulkInput = instantIpeForm.querySelector('textarea');
    const formatBulk = () => {
        const ids = bulkInput.value.toUpperCase().split(/[\s,;]+/).filter(Boolean);
        if (ids.every((id) => /^[A-Z0-9]+$/.test(id) && id.length % 15 === 0)) {
            bulkInput.value = ids.flatMap((id) => id.match(/.{15}/g)).join('\n');
        }
    };

    instantIpeForm.querySelectorAll('[name="mode"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            sections.forEach((section) => {
                const enabled = radio.value === section.dataset.ipeMode;
                section.hidden = !enabled;
                section.querySelector('[name="tracking_ids"]').disabled = !enabled;
            });
        });
    });

    sections.forEach((section) => {
        const input = section.querySelector('[name="tracking_ids"]');
        input.addEventListener('input', () => {
            const { selectionStart, selectionEnd } = input;
            input.value = input.value.toUpperCase();
            input.setSelectionRange(selectionStart, selectionEnd);
        });
    });
    bulkInput.addEventListener('blur', formatBulk);
    bulkInput.addEventListener('paste', () => setTimeout(formatBulk, 0));
    instantIpeForm.addEventListener('submit', formatBulk);
}

const ipeHistory = document.querySelector('[data-ipe-history]');
if (ipeHistory) {
    let isRefreshing = false;
    const refreshHistory = async () => {
        if (document.hidden || isRefreshing || ipeHistory.contains(document.activeElement)) return;
        isRefreshing = true;
        try {
            const url = new URL(ipeHistory.dataset.ipeHistory);
            url.search = window.location.search;
            const response = await fetch(url, { cache: 'no-store' });
            if (response.ok && !response.redirected && !ipeHistory.contains(document.activeElement)) {
                ipeHistory.innerHTML = await response.text();
            }
        } catch {
            // The next refresh can recover from a transient connection failure.
        } finally {
            isRefreshing = false;
        }
    };
    setInterval(refreshHistory, 15000);
    document.addEventListener('visibilitychange', refreshHistory);
}

const copyReport = document.querySelector('[data-copy-report]');
if (copyReport) {
    copyReport.addEventListener('click', async () => {
        const report = document.getElementById(copyReport.dataset.copyReport);
        const feedback = document.querySelector('[data-report-feedback]');
        try {
            await navigator.clipboard.writeText(report.value);
            feedback.textContent = 'Report copied.';
        } catch {
            report.select();
            feedback.textContent = 'Report selected. Copy it using your keyboard.';
        }
    });
}
