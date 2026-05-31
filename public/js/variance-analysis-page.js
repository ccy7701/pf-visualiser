(function () {
    const config = window.varianceAnalysisConfig || {};
    const scenarios = config.initialScenarios || [];
    const showScenarioBase = config.showScenarioBase || '';
    const saveActualsBase = config.saveActualsBase || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });
    const expenseCategories = [
        { id: 'food', name: 'Food' },
        { id: 'groceries', name: 'Groceries' },
        { id: 'personal_care', name: 'Personal care' },
        { id: 'subscriptions', name: 'Subscriptions' },
        { id: 'household', name: 'Household' },
        { id: 'health', name: 'Health' },
        { id: 'apparel', name: 'Apparel' },
        { id: 'transportation', name: 'Transportation' },
        { id: 'entertainment', name: 'Entertainment' },
        { id: 'prepaid_reload', name: 'Prepaid reload' },
        { id: 'books_stationery', name: 'Books and stationery' },
        { id: 'others', name: 'Others' },
    ];

    let loadedScenarioId = null;
    let projectedMonths = [];
    let actualByMonth = new Map();
    let selectedMonth = null;
    let statusTimer = null;
    let actualInputsEnabled = false;

    function toNumber(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeNullableNumber(value) {
        if (value === null || value === undefined) return null;
        const raw = String(value).trim();
        if (raw === '') return null;
        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
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
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('#vaInputTabs [data-bs-toggle="tab"]').forEach((el) => {
                const existing = bootstrap.Tooltip.getInstance(el);
                if (!existing) {
                    new bootstrap.Tooltip(el, {
                        trigger: 'hover focus',
                    });
                }
            });
        }

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
            opening_coh: normalizeNullableNumber(row.opening_coh),
            net_income: normalizeNullableNumber(row.net_income),
            expenses: normalizeNullableNumber(row.expenses),
            debt_servicing: normalizeNullableNumber(row.debt_servicing),
            closing_coh: normalizeNullableNumber(row.closing_coh),
            closing_elr: normalizeNullableNumber(row.closing_elr),
            closing_epf: normalizeNullableNumber(row.closing_epf),
            notes: row.notes || null,
            expense_breakdown: normalizeExpenseBreakdown(row.expense_breakdown),
        };
    }

    function hasAnyValue(row) {
        if (!row) return false;

        const scalarFields = ['opening_coh', 'net_income', 'expenses', 'debt_servicing', 'closing_coh', 'closing_elr', 'closing_epf'];
        if (scalarFields.some((field) => row[field] !== null && row[field] !== undefined)) {
            return true;
        }

        return Array.isArray(row.expense_breakdown) && row.expense_breakdown.some((item) => toNumber(item.amount, 0) > 0);
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

    function setSelectedMonth(month) {
        if (!month) return;

        selectedMonth = month;

        renderActualInputs();
        renderRows();
    }

    function projectedByMonth(month) {
        return projectedMonths.find((row) => row.month === month) || null;
    }

    function expenseAmountForCategory(actualRow, categoryId) {
        if (!actualRow || !Array.isArray(actualRow.expense_breakdown)) return 0;

        const item = actualRow.expense_breakdown.find((entry) => entry.category_id === categoryId);
        return item ? toNumber(item.amount, 0) : 0;
    }

    function setExpenseAmountForCategory(actualRow, category, amount) {
        if (!actualRow) return;
        const normalizedAmount = Math.max(0, toNumber(amount, 0));

        if (!Array.isArray(actualRow.expense_breakdown)) {
            actualRow.expense_breakdown = [];
        }

        const existing = actualRow.expense_breakdown.find((entry) => entry.category_id === category.id);
        if (existing) {
            existing.amount = normalizedAmount;
            existing.name = category.name;
        } else {
            actualRow.expense_breakdown.push({
                category_id: category.id,
                name: category.name,
                amount: normalizedAmount,
            });
        }
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
            const amount = expenseAmountForCategory(actualRow, category.id);

            row.innerHTML = `
                <td>${category.name}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">RM</span>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-control compact-input text-end va-input-control"
                            data-expense-category-id="${category.id}"
                            value="${amount.toFixed(2)}"
                            ${actualInputsEnabled ? '' : 'disabled'}
                        >
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });

        tbody.querySelectorAll('input[data-expense-category-id]').forEach((input) => {
            input.addEventListener('input', () => {
                const month = selectedMonth;
                const actual = ensureActualMonth(month);
                if (!actual) return;

                const categoryId = input.dataset.expenseCategoryId;
                const category = expenseCategories.find((item) => item.id === categoryId);
                if (!category) return;

                setExpenseAmountForCategory(actual, category, input.value);
                const total = recalculateActualExpenses(actual);
                updateExpensesTotalDisplay(total);
                renderRows();
            });
        });
    }

    function renderActualInputs() {
        const fieldset = document.getElementById('actualInputsFieldset');
        const monthDisplay = document.getElementById('targetMonthDisplay');
        const closingCohInput = document.getElementById('actualClosingCoh');
        const closingElrInput = document.getElementById('actualClosingElr');
        const closingEpfInput = document.getElementById('actualClosingEpf');

        if (!fieldset || !monthDisplay || !closingCohInput || !closingElrInput || !closingEpfInput) {
            return;
        }

        const isReady = loadedScenarioId !== null && projectedMonths.length > 0 && selectedMonth !== null;
        actualInputsEnabled = isReady;
        fieldset.querySelectorAll('.va-input-control').forEach((input) => {
            input.disabled = !isReady;
        });

        if (!isReady) {
            closingCohInput.value = '';
            closingElrInput.value = '';
            closingEpfInput.value = '';
            monthDisplay.textContent = '-';
            updateExpensesTotalDisplay(0);
            document.getElementById('actualExpenseCategoryRows').innerHTML = '';
            return;
        }

        const month = selectedMonth;
        monthDisplay.textContent = formatMonthLabel(month);
        const actual = ensureActualMonth(month);

        closingCohInput.value = actual.closing_coh ?? '';
        closingElrInput.value = actual.closing_elr ?? '';
        closingEpfInput.value = actual.closing_epf ?? '';

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
        const saveBtn = document.getElementById('saveActualsBtn');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!projectedMonths.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">No projected months found for this scenario.</td></tr>';
            if (saveBtn) saveBtn.disabled = true;
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
            `;

            const handleSelect = () => {
                setSelectedMonth(projected.month);
            };

            row.addEventListener('click', handleSelect);
            tbody.appendChild(row);
        });

        if (saveBtn) saveBtn.disabled = false;
    }

    function buildActualSavePayload() {
        return Array.from(actualByMonth.values())
            .filter((row) => hasAnyValue(row))
            .map((row) => ({
                month: row.month,
                opening_coh: row.opening_coh,
                net_income: row.net_income,
                expenses: row.expenses,
                debt_servicing: row.debt_servicing,
                closing_coh: row.closing_coh,
                closing_elr: row.closing_elr,
                closing_epf: row.closing_epf,
                notes: row.notes,
                expense_breakdown: (row.expense_breakdown || []).map((item) => ({
                    category_id: item.category_id,
                    name: item.name,
                    amount: toNumber(item.amount, 0),
                })),
            }));
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
        projectedMonths = Array.isArray(data?.projected_months) ? data.projected_months : [];
        actualByMonth = buildActualByMonth(data?.actual_months || []);
        selectedMonth = null;

        renderActualInputs();
        renderRows();
        setStatus('Scenario loaded. Click a month row to enter actual values.');
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

    document.getElementById('actualClosingCoh')?.addEventListener('input', (event) => {
        const actual = ensureActualMonth(selectedMonth);
        if (!actual) return;

        actual.closing_coh = normalizeNullableNumber(event.target.value);
        renderRows();
    });

    document.getElementById('actualClosingElr')?.addEventListener('input', (event) => {
        const actual = ensureActualMonth(selectedMonth);
        if (!actual) return;

        actual.closing_elr = normalizeNullableNumber(event.target.value);
        renderRows();
    });

    document.getElementById('actualClosingEpf')?.addEventListener('input', (event) => {
        const actual = ensureActualMonth(selectedMonth);
        if (!actual) return;

        actual.closing_epf = normalizeNullableNumber(event.target.value);
        renderRows();
    });

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
    initActualInputTabUI();
    renderActualInputs();
})();
