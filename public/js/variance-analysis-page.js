(function () {
    const config = window.varianceAnalysisConfig || {};
    const scenarios = config.initialScenarios || [];
    const showScenarioBase = config.showScenarioBase || '';
    const saveActualsBase = config.saveActualsBase || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });

    let loadedScenarioId = null;
    let statusTimer = null;

    function toNumber(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatMonthLabel(month) {
        if (!month || month.length < 7) return month || '';
        const d = new Date(`${month}-01T00:00:00`);
        return Number.isNaN(d.getTime()) ? month : monthLabelFormatter.format(d);
    }

    function formatVariance(value) {
        const n = toNumber(value, 0);
        const prefix = n > 0 ? '+' : '';
        return `${prefix}${money.format(n)}`;
    }

    function setStatus(message, isError = false) {
        const el = document.getElementById('statusMessage');
        if (!el) return;

        el.textContent = message;
        el.classList.toggle('is-error', isError);
        el.classList.add('is-visible');

        if (statusTimer) clearTimeout(statusTimer);
        statusTimer = setTimeout(() => {
            el.classList.remove('is-visible');
        }, 3200);
    }

    function populateScenarioSelect() {
        const select = document.getElementById('scenarioSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Select a saved scenario...</option>';
        scenarios.forEach((scenario) => {
            const option = document.createElement('option');
            option.value = String(scenario.id);
            option.textContent = scenario.name;
            select.appendChild(option);
        });
    }

    function findActualByMonth(actualMonths) {
        const map = new Map();
        (actualMonths || []).forEach((row) => {
            if (row?.month) {
                map.set(row.month, row);
            }
        });
        return map;
    }

    function renderRows(projectedMonths, actualMonths) {
        const tbody = document.getElementById('planActualRows');
        const saveBtn = document.getElementById('saveActualsBtn');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!projectedMonths || projectedMonths.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-secondary py-4">No projected months found for this scenario.</td></tr>';
            if (saveBtn) saveBtn.disabled = true;
            return;
        }

        const actualByMonth = findActualByMonth(actualMonths);

        projectedMonths.forEach((projected) => {
            const actual = actualByMonth.get(projected.month) || {};
            const projectedCoh = toNumber(projected.closing_coh, 0);
            const projectedElr = toNumber(projected.closing_elr, 0);
            const projectedEpf = toNumber(projected.closing_epf, 0);
            const actualCoh = actual.closing_coh !== null && actual.closing_coh !== undefined ? toNumber(actual.closing_coh, 0) : null;
            const actualElr = actual.closing_elr !== null && actual.closing_elr !== undefined ? toNumber(actual.closing_elr, 0) : null;
            const actualEpf = actual.closing_epf !== null && actual.closing_epf !== undefined ? toNumber(actual.closing_epf, 0) : null;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatMonthLabel(projected.month)}</td>
                <td class="text-end">${money.format(projectedCoh)}</td>
                <td class="text-end">
                    <input type="number" step="0.01" class="form-control form-control-sm text-end" data-actual-month="${projected.month}" data-actual-field="closing_coh" value="${actualCoh ?? ''}">
                </td>
                <td class="text-end" data-var-month="${projected.month}" data-var-field="closing_coh">${actualCoh === null ? '-' : formatVariance(actualCoh - projectedCoh)}</td>

                <td class="text-end">${money.format(projectedElr)}</td>
                <td class="text-end">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" data-actual-month="${projected.month}" data-actual-field="closing_elr" value="${actualElr ?? ''}">
                </td>
                <td class="text-end" data-var-month="${projected.month}" data-var-field="closing_elr">${actualElr === null ? '-' : formatVariance(actualElr - projectedElr)}</td>

                <td class="text-end">${money.format(projectedEpf)}</td>
                <td class="text-end">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" data-actual-month="${projected.month}" data-actual-field="closing_epf" value="${actualEpf ?? ''}">
                </td>
                <td class="text-end" data-var-month="${projected.month}" data-var-field="closing_epf">${actualEpf === null ? '-' : formatVariance(actualEpf - projectedEpf)}</td>
            `;
            tbody.appendChild(tr);
        });

        if (saveBtn) saveBtn.disabled = false;

        tbody.querySelectorAll('input[data-actual-month]').forEach((input) => {
            input.addEventListener('input', () => {
                recalculateVarianceRow(input.dataset.actualMonth, projectedMonths);
            });
        });
    }

    function recalculateVarianceRow(month, projectedMonths) {
        const projected = (projectedMonths || []).find((row) => row.month === month);
        if (!projected) return;

        ['closing_coh', 'closing_elr', 'closing_epf'].forEach((field) => {
            const input = document.querySelector(`input[data-actual-month="${month}"][data-actual-field="${field}"]`);
            const target = document.querySelector(`[data-var-month="${month}"][data-var-field="${field}"]`);
            if (!input || !target) return;

            if (String(input.value).trim() === '') {
                target.textContent = '-';
                return;
            }

            const actualValue = toNumber(input.value, 0);
            const projectedValue = toNumber(projected[field], 0);
            target.textContent = formatVariance(actualValue - projectedValue);
        });
    }

    function buildActualSavePayload() {
        const rows = new Map();

        document.querySelectorAll('input[data-actual-month]').forEach((input) => {
            const month = input.dataset.actualMonth;
            const field = input.dataset.actualField;
            if (!month || !field) return;

            if (!rows.has(month)) {
                rows.set(month, { month });
            }

            rows.get(month)[field] = String(input.value).trim() === '' ? null : toNumber(input.value, 0);
        });

        return Array.from(rows.values());
    }

    async function loadScenario() {
        const scenarioSelect = document.getElementById('scenarioSelect');
        const scenarioId = scenarioSelect?.value;

        if (!scenarioId) {
            setStatus('Select a scenario first.', true);
            return;
        }

        setStatus('Loading scenario...');

        const response = await fetch(`${showScenarioBase}/${scenarioId}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data?.message || 'Unable to load scenario.');
        }

        loadedScenarioId = Number(data?.scenario?.id || 0);
        document.getElementById('loadedScenarioName').textContent = data?.scenario?.name || '-';
        document.getElementById('loadedScenarioUpdatedAt').textContent = data?.scenario?.updated_at || '-';
        renderRows(data?.projected_months || [], data?.actual_months || []);
        setStatus('Scenario loaded.');

        return data?.projected_months || [];
    }

    async function saveActuals() {
        if (!loadedScenarioId) {
            setStatus('Load a scenario before saving.', true);
            return;
        }

        setStatus('Saving actual values...');

        const response = await fetch(`${saveActualsBase}/${loadedScenarioId}/actuals`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                actuals: buildActualSavePayload(),
            }),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const msg = data?.message || Object.values(data?.errors || {}).flat().join(' ') || 'Unable to save actual values.';
            throw new Error(msg);
        }

        setStatus(data?.message || 'Actual values saved successfully.');
    }

    document.getElementById('loadScenarioBtn')?.addEventListener('click', async () => {
        try {
            await loadScenario();
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    document.getElementById('saveActualsBtn')?.addEventListener('click', async () => {
        try {
            await saveActuals();
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    populateScenarioSelect();
})();
