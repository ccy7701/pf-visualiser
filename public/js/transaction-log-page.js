(() => {
    const config = window.counterPageConfig || {};
    const snapshot = config.snapshot || {};

    let expectedValue = Number(snapshot.expected_counter ?? snapshot.counter ?? 0);
    let accruedSalaryValue = Number(snapshot.accrued_salary || 0);
    let incrementPerSecond = Number(snapshot.increment_per_second || 0);

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function renderAccruedSalary() {
        const el = document.getElementById('accruedSalarySummary');
        if (el) {
            el.textContent = `RM ${formatter.format(accruedSalaryValue)}`;
        }
    }

    function renderDynamicTotal() {
        const el = document.getElementById('dynamicTotalSummary');
        if (el) {
            el.textContent = `RM ${formatter.format(expectedValue)}`;
        }
    }

    function renderSummary() {
        renderAccruedSalary();
        renderDynamicTotal();
    }

    async function syncSnapshot() {
        if (!config.snapshotUrl) return;

        const response = await fetch(config.snapshotUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        const data = await response.json();
        expectedValue = Number(data.expected_counter ?? data.counter ?? 0);
        accruedSalaryValue = Number(data.accrued_salary);
        incrementPerSecond = Number(data.increment_per_second);
        renderSummary();
    }

    function tick() {
        expectedValue += incrementPerSecond;
        accruedSalaryValue += incrementPerSecond;
        renderSummary();
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    renderSummary();
    setInterval(tick, 1000);
    setInterval(syncSnapshot, 60000);
    window.addEventListener('counter-updated', syncSnapshot);

    const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
    applyTheme(storedTheme);

    window.addEventListener('theme-changed', (e) => {
        applyTheme(e.detail.theme);
    });
})();
