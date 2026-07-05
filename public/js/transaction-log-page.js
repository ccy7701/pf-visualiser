(() => {
    const config = window.counterPageConfig || {};
    const snapshot = config.snapshot || {};

    let projectedEotmTfpValue = Number(snapshot.projected_eotm_tfp ?? snapshot.expected_counter ?? snapshot.counter ?? 0);
    let unpaidAccrualValue = Number(snapshot.current_month_unpaid_accrual ?? snapshot.accrued_salary ?? 0);
    let startingAmountValue = Number(snapshot.current_month_starting_amount ?? snapshot.starting_amount ?? 0);
    let netTransactionsValue = Number(snapshot.current_month_net_transactions || 0);
    let incrementPerSecond = Number(snapshot.increment_per_second || 0);

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function formatRm(value) {
        return `RM ${formatter.format(value)}`;
    }

    function setMoneyText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = formatRm(value);
        }

        return el;
    }

    function setIncrementingState(el) {
        if (el) {
            el.classList.toggle('counter-live-incrementing', incrementPerSecond > 0);
        }
    }

    function renderNetTransactions() {
        const el = document.getElementById('netTransactionsSummary');
        if (!el) return;

        el.textContent = formatRm(netTransactionsValue);
        el.classList.toggle('text-success', netTransactionsValue > 0);
        el.classList.toggle('text-danger', netTransactionsValue <= 0);
    }

    function renderUnpaidAccrual() {
        setIncrementingState(setMoneyText('unpaidAccrualSummary', unpaidAccrualValue));
    }

    function renderProjectedEotmTfp() {
        setIncrementingState(setMoneyText('projectedEotmTfpSummary', projectedEotmTfpValue));
    }

    function renderStartingAmount() {
        setMoneyText('startingAmountSummary', startingAmountValue);
    }

    function readSnapshotValues(data) {
        startingAmountValue = Number(data.current_month_starting_amount ?? data.starting_amount ?? 0);
        netTransactionsValue = Number(data.current_month_net_transactions || 0);
        unpaidAccrualValue = Number(data.current_month_unpaid_accrual ?? data.accrued_salary ?? 0);
        projectedEotmTfpValue = Number(
            data.projected_eotm_tfp
                ?? (startingAmountValue + netTransactionsValue + unpaidAccrualValue)
        );
        incrementPerSecond = Number(data.increment_per_second || 0);
    }

    function syncLegacySummaryIds() {
        const accruedEl = document.getElementById('accruedSalarySummary');
        if (accruedEl) {
            accruedEl.textContent = formatRm(unpaidAccrualValue);
        }

        const totalEl = document.getElementById('dynamicTotalSummary');
        if (totalEl) {
            totalEl.textContent = formatRm(projectedEotmTfpValue);
        }
    }

    function renderSummary() {
        renderStartingAmount();
        renderNetTransactions();
        renderUnpaidAccrual();
        renderProjectedEotmTfp();
        syncLegacySummaryIds();
    }

    async function syncSnapshot() {
        if (!config.snapshotUrl) return;

        const response = await fetch(config.snapshotUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        const data = await response.json();
        readSnapshotValues(data);
        renderSummary();
    }

    function tick() {
        projectedEotmTfpValue += incrementPerSecond;
        unpaidAccrualValue += incrementPerSecond;
        renderSummary();
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    const statusEl = document.getElementById('statusMessage');
    let statusTimer;

    window.addEventListener('transaction-toast', (event) => {
        if (!statusEl) return;

        statusEl.textContent = event.detail.message;
        statusEl.classList.add('is-visible');
        if (statusTimer) clearTimeout(statusTimer);
        statusTimer = setTimeout(() => {
            statusEl.classList.remove('is-visible');
        }, 3200);
    });

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
