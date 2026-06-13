(function () {
    const config = window.varianceAnalysisConfig || {};
    const scenarios = config.initialScenarios || [];
    const showScenarioBase = config.showScenarioBase || '';

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });
    let expenseCategories = Array.isArray(config.expenseCategories) ? config.expenseCategories : [];

    let loadedScenarioId = null;
    let projectedMonths = [];
    let actualByMonth = new Map();
    let selectedMonth = null;
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

    function formatAmountOrDash(value) {
        return value === null || value === undefined ? '-' : money.format(toNumber(value, 0));
    }

    function hasAnyBalanceValue(row) {
        return row
            && [row.closing_coh, row.closing_elr, row.closing_epf].some((value) => value !== null && value !== undefined);
    }

    function tfpFromBalances(row) {
        if (!hasAnyBalanceValue(row)) return null;

        return toNumber(row.closing_coh, 0)
            + toNumber(row.closing_elr, 0)
            + toNumber(row.closing_epf, 0);
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

        select.innerHTML = '<option value="">Select...</option>';
        scenarios.forEach((scenario) => {
            const option = document.createElement('option');
            option.value = String(scenario.id);
            option.textContent = scenario.name;
            select.appendChild(option);
        });
    }

    function initActualInputTabUI() {
        document.querySelectorAll('#vaInputTabs [data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', () => {
                document.querySelectorAll('#vaInputTabs .projection-input-tab').forEach((btn) => {
                    btn.classList.toggle('active', btn === tabButton);
                });
            });
        });
    }

    function normalizeExpenseBreakdown(items) {
        if (!Array.isArray(items)) return [];

        return items
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
                category_id: String(item.category_id || '').trim(),
                name: String(item.name || '').trim(),
                amount: toNumber(item.amount, 0),
            }))
            .filter((item) => item.category_id.length > 0);
    }

    function normalizeActualRow(row = {}, month = '') {
        return {
            month: row.month || month,
            opening_coh: row.opening_coh ?? null,
            net_income: row.net_income ?? null,
            expenses: row.expenses ?? null,
            debt_servicing: row.debt_servicing ?? null,
            closing_coh: row.closing_coh ?? null,
            closing_elr: row.closing_elr ?? null,
            closing_epf: row.closing_epf ?? null,
            notes: row.notes || null,
            expense_breakdown: normalizeExpenseBreakdown(row.expense_breakdown),
        };
    }

    function buildActualByMonth(actualMonths) {
        const map = new Map();

        (actualMonths || []).forEach((row) => {
            if (!row?.month) return;
            map.set(row.month, normalizeActualRow(row, row.month));
        });

        return map;
    }

    function ensureActualMonth(month) {
        if (!month) return null;

        if (!actualByMonth.has(month)) {
            actualByMonth.set(month, normalizeActualRow({ month }, month));
        }

        return actualByMonth.get(month);
    }

    function expenseAmountForCategory(actualRow, categoryId) {
        if (!actualRow || !Array.isArray(actualRow.expense_breakdown)) return 0;

        const item = actualRow.expense_breakdown.find((entry) => entry.category_id === categoryId);
        return item ? toNumber(item.amount, 0) : 0;
    }

    function recalculateActualExpenses(actualRow) {
        if (!actualRow) return 0;

        const total = (actualRow.expense_breakdown || []).reduce((carry, item) => carry + toNumber(item.amount, 0), 0);
        actualRow.expenses = total;
        return total;
    }

    function updateExpensesTotalDisplay(total) {
        const el = document.getElementById('actualExpensesTotal');
        if (!el) return;

        el.textContent = `RM ${money.format(total)}`;
    }

    function renderExpenseCategoryRows(actualRow) {
        const tbody = document.getElementById('actualExpenseCategoryRows');
        if (!tbody) return;

        tbody.innerHTML = '';

        expenseCategories.forEach((category) => {
            const row = document.createElement('tr');
            const amount = expenseAmountForCategory(actualRow, String(category.id));

            row.innerHTML = `
                <td>${category.name}</td>
                <td class="text-end">${money.format(amount)}</td>
            `;

            tbody.appendChild(row);
        });
    }

    function renderActualInputs() {
        const fieldset = document.getElementById('actualInputsFieldset');
        const monthDisplay = document.getElementById('targetMonthDisplay');
        const closingCohInput = document.getElementById('actualClosingCoh');
        const closingElrInput = document.getElementById('actualClosingElr');
        const closingEpfInput = document.getElementById('actualClosingEpf');
        const tfpInput = document.getElementById('actualTfp');

        if (!fieldset || !monthDisplay || !closingCohInput || !closingElrInput || !closingEpfInput || !tfpInput) {
            return;
        }

        const isReady = loadedScenarioId !== null && projectedMonths.length > 0 && selectedMonth !== null;
        fieldset.querySelectorAll('.va-input-control').forEach((input) => {
            input.disabled = !isReady;
        });

        if (!isReady) {
            closingCohInput.value = '';
            closingElrInput.value = '';
            closingEpfInput.value = '';
            tfpInput.value = '';
            monthDisplay.textContent = '-';
            updateExpensesTotalDisplay(0);
            document.getElementById('actualExpenseCategoryRows').innerHTML = '';
            return;
        }

        const month = selectedMonth;
        monthDisplay.textContent = formatMonthLabel(month);
        const actual = ensureActualMonth(month);

        closingCohInput.value = actual.closing_coh === null ? '' : money.format(actual.closing_coh);
        closingElrInput.value = actual.closing_elr === null ? '' : money.format(actual.closing_elr);
        closingEpfInput.value = actual.closing_epf === null ? '' : money.format(actual.closing_epf);
        tfpInput.value = formatAmountOrDash(tfpFromBalances(actual));

        const totalExpenses = recalculateActualExpenses(actual);
        updateExpensesTotalDisplay(totalExpenses);
        renderExpenseCategoryRows(actual);
    }

    function varianceCell(actualValue, projectedValue) {
        if (actualValue === null || actualValue === undefined) {
            return { text: '-', className: '' };
        }

        const delta = toNumber(actualValue, 0) - toNumber(projectedValue, 0);

        if (delta > 0) {
            return { text: formatVariance(delta), className: 'va-positive' };
        }

        if (delta < 0) {
            return { text: formatVariance(delta), className: 'va-negative' };
        }

        return { text: formatVariance(delta), className: '' };
    }

    function renderRows() {
        const tbody = document.getElementById('planActualRows');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!projectedMonths.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">No projected months found for this scenario.</td></tr>';
            return;
        }

        function metricCellHtml(actualValue, projectedValue) {
            return `
                <span class="va-value">Actual: ${formatAmountOrDash(actualValue)}</span>
                <span class="va-projected">Projected: ${money.format(toNumber(projectedValue, 0))}</span>
            `;
        }

        projectedMonths.forEach((projected) => {
            const actual = actualByMonth.get(projected.month) || normalizeActualRow({ month: projected.month }, projected.month);
            const cohVariance = varianceCell(actual.closing_coh, projected.closing_coh);
            const elrVariance = varianceCell(actual.closing_elr, projected.closing_elr);
            const epfVariance = varianceCell(actual.closing_epf, projected.closing_epf);
            const actualTfp = tfpFromBalances(actual);
            const projectedTfp = tfpFromBalances(projected);
            const tfpVariance = varianceCell(actualTfp, projectedTfp);

            const row = document.createElement('tr');
            const isSelected = projected.month === selectedMonth;

            row.classList.toggle('table-primary', isSelected);
            row.style.cursor = 'pointer';

            row.innerHTML = `
                <td class="va-month-cell">${formatMonthLabel(projected.month)}</td>
                <td class="va-metric-cell">${metricCellHtml(actual.closing_coh, projected.closing_coh)}</td>
                <td class="va-variance-cell ${cohVariance.className}">${cohVariance.text}</td>
                <td class="va-metric-cell">${metricCellHtml(actual.closing_elr, projected.closing_elr)}</td>
                <td class="va-variance-cell ${elrVariance.className}">${elrVariance.text}</td>
                <td class="va-metric-cell">${metricCellHtml(actual.closing_epf, projected.closing_epf)}</td>
                <td class="va-variance-cell ${epfVariance.className}">${epfVariance.text}</td>
                <td class="va-metric-cell">${metricCellHtml(actualTfp, projectedTfp)}</td>
                <td class="va-variance-cell ${tfpVariance.className}">${tfpVariance.text}</td>
            `;

            row.addEventListener('click', () => {
                selectedMonth = projected.month;
                renderActualInputs();
                renderRows();
            });
            tbody.appendChild(row);
        });
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
        expenseCategories = Array.isArray(data?.expense_categories) ? data.expense_categories : expenseCategories;
        projectedMonths = Array.isArray(data?.projected_months) ? data.projected_months : [];
        actualByMonth = buildActualByMonth(data?.actual_months || []);
        selectedMonth = null;

        renderActualInputs();
        renderRows();
        setStatus('Scenario loaded. Click a month row to view actual values from History.');
    }

    document.getElementById('loadScenarioBtn')?.addEventListener('click', async () => {
        try {
            await loadScenario();
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    populateScenarioSelect();
    initActualInputTabUI();
    renderActualInputs();
})();
