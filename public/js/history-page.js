(function () {
    const config = window.historyConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });

    const expenseCategories = config.expenseCategories || [];
    const incomeCategories = config.incomeCategories || [];

    let cohChart = null;
    let cohBreakdownChart = null;
    let incomeExpenseChart = null;
    let months = [];
    let selectedMonth = config.latestMonth || '';
    let activeHistoryVisualisation = 'coh';
    let counterSnapshot = null;
    let showCurrentAccrualOverlay = false;
    const deselectedExpenseWaffleCategoryIds = new Set();
    let expenseWaffleValueMode = 'sen';
    let statusTimer = null;
    const categoryPalette = [
        '#0d6efd',
        '#198754',
        '#dc3545',
        '#fd7e14',
        '#6f42c1',
        '#20c997',
        '#d63384',
        '#ffc107',
        '#0dcaf0',
        '#6c757d',
        '#6610f2',
        '#2b8a3e',
    ];

    function toNumber(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatMonthLabel(month) {
        if (!month || month.length < 7) return month || '';
        const d = new Date(`${month}-01T00:00:00`);
        return Number.isNaN(d.getTime()) ? month : monthLabelFormatter.format(d);
    }

    function toMonthOrNull(value) {
        if (!value) return null;

        const raw = String(value).trim();
        const match = raw.match(/^(\d{4})-(\d{2})/);
        if (!match) return null;

        const month = Number(match[2]);
        return month >= 1 && month <= 12 ? `${match[1]}-${match[2]}` : null;
    }

    function shiftMonth(month, delta) {
        if (!month || month.length < 7) return month;

        const d = new Date(`${month}-01T00:00:00`);
        if (Number.isNaN(d.getTime())) return month;

        d.setMonth(d.getMonth() + delta);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
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
        }, 3000);
    }

    function normalizeBreakdown(items, categories) {
        const amountByCategory = new Map();

        (Array.isArray(items) ? items : []).forEach((item) => {
            amountByCategory.set(Number(item.category_id), toNumber(item.amount, 0));
        });

        return categories.map((category) => ({
            category_id: Number(category.id),
            name: category.name,
            amount: amountByCategory.get(Number(category.id)) || 0,
        }));
    }

    function findMonth(month) {
        return months.find((row) => row.month === month) || null;
    }

    function total(items) {
        return (items || []).reduce((carry, item) => carry + toNumber(item.amount, 0), 0);
    }

    function balanceTotalFromValues(closingCoh, closingElr, closingEpf) {
        return toNumber(closingCoh, 0) + toNumber(closingElr, 0) + toNumber(closingEpf, 0);
    }

    function balanceTotalForRow(row) {
        if (!row) return 0;

        return balanceTotalFromValues(row.closing_coh, row.closing_elr, row.closing_epf);
    }

    function currentMonthKey() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    function latestKnownRetirementBalances(beforeMonth) {
        const currentIndex = months.findIndex((row) => row.month === beforeMonth);
        const searchRows = currentIndex >= 0 ? months.slice(0, currentIndex + 1) : months;

        for (let index = searchRows.length - 1; index >= 0; index--) {
            const row = searchRows[index];
            if (row && (row.closing_elr !== null || row.closing_epf !== null)) {
                return {
                    elr: toNumber(row.closing_elr, 0),
                    epf: toNumber(row.closing_epf, 0),
                };
            }
        }

        return { elr: 0, epf: 0 };
    }

    function currentAccruedBalanceForRow(row) {
        if (!showCurrentAccrualOverlay || !counterSnapshot || row?.month !== currentMonthKey()) {
            return null;
        }

        const retirementBalances = latestKnownRetirementBalances(row.month);
        const currentTotal = row.has_record
            ? balanceTotalForRow(row)
            : balanceTotalFromValues(counterSnapshot.actual_counter, retirementBalances.elr, retirementBalances.epf);

        return currentTotal + toNumber(counterSnapshot.accrued_salary, 0);
    }

    function currentBalanceOverlayDatasets() {
        if (!showCurrentAccrualOverlay || !counterSnapshot) return [];

        const currentMonth = currentMonthKey();
        const currentIndex = months.findIndex((row) => row.month === currentMonth);
        if (currentIndex < 0) return [];

        const currentRow = months[currentIndex];
        const accruedCurrentTotal = currentAccruedBalanceForRow(currentRow);

        const accruedData = months.map(() => null);
        if (currentIndex > 0) {
            accruedData[currentIndex - 1] = balanceTotalForRow(months[currentIndex - 1]);
        }
        accruedData[currentIndex] = accruedCurrentTotal;

        return [
            {
                label: 'Total Balance + Accrual',
                data: accruedData,
                borderColor: 'rgba(25, 135, 84, 0.42)',
                backgroundColor: 'rgba(25, 135, 84, 0.08)',
                borderWidth: 2,
                borderDash: [5, 4],
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0,
                spanGaps: true,
            },
        ];
    }

    function percentOfTotal(value, totalValue) {
        return totalValue > 0 ? (toNumber(value, 0) / totalValue) * 100 : 0;
    }

    function formatSenPerRinggit(value, totalValue) {
        const percentage = percentOfTotal(value, totalValue);
        return `${Math.round(percentage)} sen/RM`;
    }

    function formatExpenseWaffleValue(value, totalValue) {
        if (expenseWaffleValueMode === 'rm') {
            return `RM ${money.format(value)}`;
        }

        return formatSenPerRinggit(value, totalValue);
    }

    function allocateExpenseWaffleCells(breakdown, totalExpenses) {
        const allocations = breakdown.map((item, index) => {
            const exactCells = percentOfTotal(item.amount, totalExpenses);
            const cellCount = Math.floor(exactCells);

            return {
                ...item,
                index,
                cellCount,
                remainder: exactCells - cellCount,
            };
        });
        let remainingCells = 100 - allocations.reduce((sum, item) => sum + item.cellCount, 0);

        [...allocations]
            .sort((a, b) => b.remainder - a.remainder || a.index - b.index)
            .slice(0, remainingCells)
            .forEach((item) => {
                allocations[item.index].cellCount += 1;
            });

        return allocations;
    }

    function updateExpenseWaffleSelectionState(chart) {
        if (!chart) return;

        chart.querySelectorAll('[data-expense-category-id]').forEach((element) => {
            const categoryId = Number(element.dataset.expenseCategoryId);
            const isSelected = !deselectedExpenseWaffleCategoryIds.has(categoryId);
            element.classList.toggle('is-deselected', !isSelected);
            if (element.matches('.history-waffle-legend-item')) {
                element.setAttribute('aria-pressed', String(isSelected));
            }
        });
    }

    function toggleExpenseWaffleCategory(chart, categoryId) {
        if (deselectedExpenseWaffleCategoryIds.has(categoryId)) {
            deselectedExpenseWaffleCategoryIds.delete(categoryId);
        } else {
            deselectedExpenseWaffleCategoryIds.add(categoryId);
        }

        updateExpenseWaffleSelectionState(chart);
    }

    function renderCategoryInputs(containerId, categories, breakdown, inputClass) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const amountByCategory = new Map();
        normalizeBreakdown(breakdown, categories).forEach((item) => {
            amountByCategory.set(Number(item.category_id), item.amount);
        });

        container.innerHTML = '';
        categories.forEach((category) => {
            const id = `${containerId}-${category.id}`;
            const cell = document.createElement('div');
            cell.className = 'history-category-cell';

            const label = document.createElement('label');
            label.setAttribute('for', id);
            label.textContent = category.name;

            const group = document.createElement('div');
            group.className = 'input-group input-group-sm';

            const prefix = document.createElement('span');
            prefix.className = 'input-group-text';
            prefix.textContent = 'RM';

            const input = document.createElement('input');
            input.id = id;
            input.className = `form-control compact-input ${inputClass}`;
            input.type = 'number';
            input.min = '0';
            input.step = '0.01';
            input.dataset.categoryId = String(category.id);
            input.dataset.categoryName = category.name;
            input.value = String(amountByCategory.get(Number(category.id)) || 0);

            input.addEventListener('input', () => {
                updateTotals();
                if (inputClass === 'history-expense-input' && activeHistoryVisualisation === 'expense-category') {
                    renderExpenseCategoryChart();
                }
            });

            group.appendChild(prefix);
            group.appendChild(input);
            cell.appendChild(label);
            cell.appendChild(group);
            container.appendChild(cell);
        });

        const totalRow = document.createElement('div');
        totalRow.className = `history-category-row history-category-total-row ${inputClass}-total`;
        totalRow.innerHTML = `
            <div class="history-category-total-label">Total</div>
            <div class="history-category-total-value">RM <span class="${inputClass}-total-value">0.00</span></div>
        `;
        container.appendChild(totalRow);
    }

    function collectBreakdown(inputClass) {
        return Array.from(document.querySelectorAll(`.${inputClass}`)).map((input) => ({
            category_id: Number(input.dataset.categoryId),
            name: input.dataset.categoryName || '',
            amount: Math.max(0, toNumber(input.value, 0)),
        }));
    }

    function updateTotals() {
        const expenseTotal = total(collectBreakdown('history-expense-input'));
        const incomeTotal = total(collectBreakdown('history-income-input'));

        const expenseEls = document.querySelectorAll('.history-expense-input-total-value');
        const incomeEls = document.querySelectorAll('.history-income-input-total-value');

        expenseEls.forEach((el) => {
            el.textContent = money.format(expenseTotal);
        });
        incomeEls.forEach((el) => {
            el.textContent = money.format(incomeTotal);
        });
    }

    function updateMonthEndTotal() {
        const totalInput = document.getElementById('monthEndTotalInput');
        if (!totalInput) return;

        const closingCoh = document.getElementById('closingCohInput')?.value;
        const closingElr = document.getElementById('closingElrInput')?.value;
        const closingEpf = document.getElementById('closingEpfInput')?.value;
        totalInput.value = balanceTotalFromValues(closingCoh, closingElr, closingEpf).toFixed(2);
    }

    function renderInputs() {
        const row = findMonth(selectedMonth);
        const monthInput = document.getElementById('historyMonth');
        const monthEndTotalInput = document.getElementById('monthEndTotalInput');
        const closingCohInput = document.getElementById('closingCohInput');
        const closingElrInput = document.getElementById('closingElrInput');
        const closingEpfInput = document.getElementById('closingEpfInput');
        const selectedMonthDisplay = document.getElementById('selectedMonthDisplay');

        if (monthInput) monthInput.value = selectedMonth;
        if (monthEndTotalInput) monthEndTotalInput.value = balanceTotalForRow(row).toFixed(2);
        if (closingCohInput) closingCohInput.value = row?.closing_coh ?? '';
        if (closingElrInput) closingElrInput.value = row?.closing_elr ?? '';
        if (closingEpfInput) closingEpfInput.value = row?.closing_epf ?? '';
        if (selectedMonthDisplay) selectedMonthDisplay.textContent = formatMonthLabel(selectedMonth);

        renderCategoryInputs('expenseInputs', expenseCategories, row?.expense_breakdown || [], 'history-expense-input');
        renderCategoryInputs('incomeInputs', incomeCategories, row?.income_breakdown || [], 'history-income-input');
        updateTotals();
    }

    function baseChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                    },
                },
                y: {
                    ticks: {
                        callback: (value) => `RM ${money.format(value)}`,
                    },
                },
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: (context) => `${context.dataset.label}: RM ${money.format(context.parsed.y)}`,
                    },
                },
            },
        };
    }

    function applySharedXAxisOptions(options, bottomPadding = 58) {
        options.scales.x.offset = true;
        options.scales.x.ticks = {
            display: false,
        };
        options.layout = {
            padding: {
                bottom: bottomPadding,
            },
        };

        return options;
    }

    function createHistoryAxisLabelPlugin(id, linesForRow) {
        return {
            id,
            afterDraw(chart) {
                const { ctx, chartArea, scales } = chart;
                const xScale = scales.x;

                if (!xScale) {
                    return;
                }

                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';

                xScale.ticks.forEach((_tick, index) => {
                    const row = months[index];
                    if (!row) {
                        return;
                    }

                    const x = xScale.getPixelForTick(index);
                    const y = chartArea.bottom + 12;
                    const lines = linesForRow(row);

                    lines.forEach((line, lineIndex) => {
                        ctx.fillStyle = line.color;
                        ctx.font = line.font || '11px system-ui, sans-serif';
                        ctx.fillText(line.text, x, y + (lineIndex * 16));
                    });
                });

                ctx.restore();
            },
        };
    }

    function renderCohChart() {
        const canvas = document.getElementById('historyCohChart');
        if (!canvas || typeof Chart === 'undefined') return;

        if (cohChart) {
            cohChart.destroy();
        }

        const options = applySharedXAxisOptions(baseChartOptions(), showCurrentAccrualOverlay && counterSnapshot ? 60 : 44);
        options.plugins.tooltip.callbacks.label = (context) => `${context.dataset.label}: RM ${money.format(context.parsed.y)}`;

        cohChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: months.map((row) => formatMonthLabel(row.month)),
                datasets: [
                    {
                        label: 'Total Balance',
                        data: months.map((row) => balanceTotalForRow(row)),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        borderWidth: 3,
                        pointRadius: 4,
                        tension: 0,
                        yAxisID: 'y',
                        spanGaps: true,
                    },
                    ...currentBalanceOverlayDatasets(),
                ],
            },
            options,
            plugins: [
                createHistoryAxisLabelPlugin('historyCohLabels', (row) => {
                    const lines = [
                        { text: formatMonthLabel(row.month), color: '#212529' },
                        { text: money.format(balanceTotalForRow(row)), color: '#0d6efd' },
                    ];
                    const accruedBalance = currentAccruedBalanceForRow(row);

                    if (accruedBalance !== null) {
                        lines.push({ text: money.format(accruedBalance), color: '#198754' });
                    }

                    return lines;
                }),
            ],
        });
    }

    function renderCohBreakdownChart() {
        const canvas = document.getElementById('historyCohBreakdownChart');
        if (!canvas || typeof Chart === 'undefined') return;

        if (cohBreakdownChart) {
            cohBreakdownChart.destroy();
        }

        const options = applySharedXAxisOptions(baseChartOptions(), 44);
        options.scales.x.stacked = true;
        options.scales.y.stacked = true;
        options.plugins.legend.display = true;
        options.plugins.legend.position = 'top';
        options.plugins.tooltip.callbacks.label = (context) => `${context.dataset.label}: RM ${money.format(context.parsed.y)}`;
        options.plugins.tooltip.callbacks.footer = (tooltipItems) => {
            const dataIndex = tooltipItems[0]?.dataIndex;
            const row = Number.isInteger(dataIndex) ? months[dataIndex] : null;
            return row ? `Total: RM ${money.format(balanceTotalForRow(row))}` : '';
        };

        cohBreakdownChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: months.map((row) => formatMonthLabel(row.month)),
                datasets: [
                    {
                        label: 'COH',
                        data: months.map((row) => toNumber(row.closing_coh, 0)),
                        backgroundColor: 'rgba(13, 110, 253, 0.76)',
                        borderColor: '#0d6efd',
                        borderWidth: 1,
                        stack: 'balances',
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                    },
                    {
                        label: 'ELR',
                        data: months.map((row) => toNumber(row.closing_elr, 0)),
                        backgroundColor: 'rgba(25, 135, 84, 0.72)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        stack: 'balances',
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                    },
                    {
                        label: 'EPF',
                        data: months.map((row) => toNumber(row.closing_epf, 0)),
                        backgroundColor: 'rgba(111, 66, 193, 0.72)',
                        borderColor: '#6f42c1',
                        borderWidth: 1,
                        stack: 'balances',
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                    },
                ],
            },
            options,
            plugins: [
                createHistoryAxisLabelPlugin('historyCohBreakdownLabels', (row) => [
                    { text: formatMonthLabel(row.month), color: '#212529' },
                ]),
            ],
        });
    }

    function renderIncomeExpenseChart() {
        const canvas = document.getElementById('historyIncomeExpenseChart');
        if (!canvas || typeof Chart === 'undefined') return;

        if (incomeExpenseChart) {
            incomeExpenseChart.destroy();
        }

        const options = applySharedXAxisOptions(baseChartOptions(), 58);

        incomeExpenseChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: months.map((row) => formatMonthLabel(row.month)),
                datasets: [
                    {
                        label: 'Income',
                        data: months.map((row) => row.total_income),
                        backgroundColor: 'rgba(25, 135, 84, 0.72)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Expenses',
                        data: months.map((row) => row.total_expenses),
                        backgroundColor: 'rgba(220, 53, 69, 0.72)',
                        borderColor: '#dc3545',
                        borderWidth: 1,
                        categoryPercentage: 0.72,
                        barPercentage: 0.78,
                        yAxisID: 'y',
                    },
                ],
            },
            options,
            plugins: [
                createHistoryAxisLabelPlugin('historyIncomeExpenseLabels', (row) => [
                    { text: formatMonthLabel(row.month), color: '#212529' },
                    { text: money.format(toNumber(row.total_income, 0)), color: '#198754' },
                    { text: money.format(toNumber(row.total_expenses, 0)), color: '#dc3545' },
                ]),
            ],
        });
    }

    function renderExpenseCategoryChart() {
        const chart = document.getElementById('historyExpenseCategoryChart');
        if (!chart) return;

        const row = findMonth(selectedMonth);
        const currentInputMonth = document.getElementById('historyMonth')?.value || selectedMonth;
        const hasRenderedExpenseInputs = document.querySelectorAll('.history-expense-input').length > 0;
        const sourceBreakdown = currentInputMonth === selectedMonth && hasRenderedExpenseInputs
            ? collectBreakdown('history-expense-input')
            : row?.expense_breakdown || [];
        const breakdown = normalizeBreakdown(sourceBreakdown, expenseCategories)
            .filter((item) => toNumber(item.amount, 0) > 0)
            .sort((a, b) => toNumber(b.amount, 0) - toNumber(a.amount, 0));
        const totalExpenses = total(breakdown);
        chart.replaceChildren();

        if (totalExpenses <= 0) {
            const emptyState = document.createElement('div');
            emptyState.className = 'history-waffle-empty';
            emptyState.textContent = 'No expense data for this month.';
            chart.appendChild(emptyState);
            chart.setAttribute('aria-label', 'Expenses by category: no expense data for this month');
            return;
        }

        const allocations = allocateExpenseWaffleCells(breakdown, totalExpenses);
        const grid = document.createElement('div');
        const legend = document.createElement('div');
        grid.className = 'history-waffle-grid';
        grid.setAttribute('aria-hidden', 'true');
        legend.className = 'history-waffle-legend';

        let cellIndex = 0;
        allocations.forEach((item) => {
            const categoryId = Number(item.category_id);
            const color = categoryPalette[item.index % categoryPalette.length];
            const formattedValue = formatExpenseWaffleValue(item.amount, totalExpenses);

            for (let index = 0; index < item.cellCount; index += 1) {
                const cell = document.createElement('span');
                cell.className = 'history-waffle-cell';
                cell.dataset.expenseCategoryId = String(categoryId);
                cell.style.backgroundColor = color;
                cell.style.gridColumn = String((cellIndex % 10) + 1);
                cell.style.gridRow = String(Math.floor(cellIndex / 10) + 1);
                cell.title = `${item.name}: ${formattedValue}`;
                cell.addEventListener('click', () => {
                    toggleExpenseWaffleCategory(chart, categoryId);
                });
                cellIndex += 1;
                grid.appendChild(cell);
            }

            const legendItem = document.createElement('button');
            const swatch = document.createElement('span');
            const name = document.createElement('span');
            const value = document.createElement('span');
            legendItem.type = 'button';
            legendItem.className = 'history-waffle-legend-item';
            legendItem.dataset.expenseCategoryId = String(categoryId);
            legendItem.setAttribute('aria-label', `${item.name}: ${formattedValue}, ${item.cellCount} of 100 squares`);
            swatch.className = 'history-waffle-swatch';
            swatch.style.backgroundColor = color;
            name.className = 'history-waffle-legend-name';
            name.textContent = item.name;
            value.className = 'history-waffle-legend-value';
            value.textContent = formattedValue;
            legendItem.append(swatch, name, value);
            legendItem.addEventListener('click', () => {
                toggleExpenseWaffleCategory(chart, categoryId);
            });
            legend.appendChild(legendItem);
        });

        chart.setAttribute('aria-label', `Expenses by category. ${allocations.map((item) => `${item.name}: ${item.cellCount} of 100 squares`).join(', ')}`);
        chart.append(grid, legend);
        updateExpenseWaffleSelectionState(chart);
    }

    function setActiveVisualisation(value) {
        activeHistoryVisualisation = ['coh', 'coh-breakdown', 'income-expense', 'expense-category'].includes(value) ? value : 'coh';

        document.getElementById('historyCohPane')?.classList.toggle('d-none', activeHistoryVisualisation !== 'coh');
        document.getElementById('historyCohBreakdownPane')?.classList.toggle('d-none', activeHistoryVisualisation !== 'coh-breakdown');
        document.getElementById('historyIncomeExpensePane')?.classList.toggle('d-none', activeHistoryVisualisation !== 'income-expense');
        document.getElementById('historyExpenseCategoryPane')?.classList.toggle('d-none', activeHistoryVisualisation !== 'expense-category');
        document.getElementById('currentAccrualControls')?.classList.toggle('d-none', activeHistoryVisualisation !== 'coh');
        document.getElementById('expenseWaffleValueControls')?.classList.toggle('d-none', activeHistoryVisualisation !== 'expense-category');
    }

    function renderActiveVisualisation() {
        setActiveVisualisation(activeHistoryVisualisation);

        if (activeHistoryVisualisation === 'coh') {
            renderCohChart();
        } else if (activeHistoryVisualisation === 'coh-breakdown') {
            renderCohBreakdownChart();
        } else if (activeHistoryVisualisation === 'income-expense') {
            renderIncomeExpenseChart();
        } else {
            renderExpenseCategoryChart();
        }
    }

    function renderAll() {
        const latestDisplay = document.getElementById('latestMonthDisplay');
        if (latestDisplay && months.length) {
            latestDisplay.textContent = formatMonthLabel(months[months.length - 1].month);
        }

        renderInputs();
        renderActiveVisualisation();
    }

    async function loadMonths(latestMonth) {
        const url = new URL(config.monthsEndpoint, window.location.origin);
        url.searchParams.set('latest_month', latestMonth);

        const response = await fetch(url.toString());
        if (!response.ok) {
            throw new Error('Unable to load history months.');
        }

        const payload = await response.json();
        months = payload.months || [];
        selectedMonth = latestMonth;
        const monthInput = document.getElementById('historyMonth');
        if (monthInput?._flatpickr) {
            monthInput._flatpickr.setDate(`${latestMonth}-01`, false);
        } else if (monthInput) {
            monthInput.value = latestMonth;
        }
        renderAll();
    }

    async function loadCounterSnapshot() {
        if (!config.counterSnapshotEndpoint) return;

        const response = await fetch(config.counterSnapshotEndpoint);
        if (!response.ok) {
            throw new Error('Unable to load current Counter snapshot.');
        }

        counterSnapshot = await response.json();
        if (activeHistoryVisualisation === 'coh' && showCurrentAccrualOverlay) {
            renderCohChart();
        }
    }

    async function saveMonth() {
        const monthInput = document.getElementById('historyMonth');
        const closingCohInput = document.getElementById('closingCohInput');
        const closingElrInput = document.getElementById('closingElrInput');
        const closingEpfInput = document.getElementById('closingEpfInput');
        const month = monthInput?.value || selectedMonth;
        const closingCoh = closingCohInput?.value;
        const closingElr = closingElrInput?.value;
        const closingEpf = closingEpfInput?.value;

        if (!month) {
            setStatus('Select a month first.', true);
            return;
        }

        if (String(closingCoh || '').trim() === '') {
            setStatus('Enter COH at month end before saving.', true);
            return;
        }

        const response = await fetch(config.saveEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                month,
                closing_coh: Number(closingCoh),
                closing_elr: String(closingElr || '').trim() === '' ? null : Number(closingElr),
                closing_epf: String(closingEpf || '').trim() === '' ? null : Number(closingEpf),
                expense_breakdown: collectBreakdown('history-expense-input'),
                income_breakdown: collectBreakdown('history-income-input'),
            }),
        });

        if (!response.ok) {
            setStatus('Unable to save history month.', true);
            return;
        }

        const payload = await response.json();
        const index = months.findIndex((row) => row.month === payload.month?.month);
        if (index >= 0) {
            months[index] = payload.month;
        }

        selectedMonth = payload.month?.month || month;
        setStatus(payload.message || 'History month saved.');
        renderAll();
    }

    function initTabs() {
        document.querySelectorAll('#historyInputTabs [data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', () => {
                document.querySelectorAll('#historyInputTabs .projection-input-tab').forEach((btn) => {
                    btn.classList.toggle('active', btn === tabButton);
                });
            });
        });
    }

    function initMonthPicker() {
        const input = document.getElementById('historyMonth');
        if (!input || typeof flatpickr === 'undefined' || typeof monthSelectPlugin === 'undefined') return;

        if (input._flatpickr) {
            input._flatpickr.destroy();
        }

        flatpickr(input, {
            plugins: [new monthSelectPlugin({ shorthand: true, dateFormat: 'Y-m', altFormat: 'M Y' })],
            altInput: true,
            altFormat: 'M Y',
            dateFormat: 'Y-m',
            allowInput: false,
            defaultDate: toMonthOrNull(input.value) ? `${toMonthOrNull(input.value)}-01` : null,
            onChange: (_selectedDates, dateStr) => {
                input.value = toMonthOrNull(dateStr) || '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
        });
    }

    function initBalanceTotalInputs() {
        ['closingCohInput', 'closingElrInput', 'closingEpfInput'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', updateMonthEndTotal);
        });

        updateMonthEndTotal();
    }

    function initCurrentAccrualOverlayToggle() {
        const input = document.getElementById('showCurrentAccrualOverlay');
        if (!input) return;

        showCurrentAccrualOverlay = input.checked;
        input.addEventListener('change', (event) => {
            showCurrentAccrualOverlay = Boolean(event.target.checked);
            if (showCurrentAccrualOverlay && !counterSnapshot) {
                loadCounterSnapshot().catch((error) => setStatus(error.message, true));
            }
            renderCohChart();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initMonthPicker();
        initBalanceTotalInputs();
        initCurrentAccrualOverlayToggle();

        document.getElementById('previousWindowBtn')?.addEventListener('click', () => {
            const latestMonth = shiftMonth(months[months.length - 1]?.month || selectedMonth, -1);
            loadMonths(latestMonth).catch((error) => setStatus(error.message, true));
        });

        document.getElementById('nextWindowBtn')?.addEventListener('click', () => {
            const latestMonth = shiftMonth(months[months.length - 1]?.month || selectedMonth, 1);
            loadMonths(latestMonth).catch((error) => setStatus(error.message, true));
        });

        document.getElementById('historyMonth')?.addEventListener('change', (event) => {
            const nextMonth = event.target.value;
            if (!nextMonth) return;
            loadMonths(nextMonth).catch((error) => setStatus(error.message, true));
        });

        document.getElementById('historyVisualisationSelect')?.addEventListener('change', (event) => {
            setActiveVisualisation(event.target.value);
            renderActiveVisualisation();
        });

        document.getElementById('saveHistoryBtn')?.addEventListener('click', () => {
            saveMonth().catch((error) => setStatus(error.message, true));
        });

        document.querySelectorAll('input[name="expenseWaffleValueMode"]').forEach((input) => {
            input.addEventListener('change', (event) => {
                expenseWaffleValueMode = event.target.value === 'rm' ? 'rm' : 'sen';
                if (activeHistoryVisualisation === 'expense-category') {
                    renderExpenseCategoryChart();
                }
            });
        });

        setActiveVisualisation(activeHistoryVisualisation);
        loadMonths(selectedMonth).catch((error) => setStatus(error.message, true));
    });
})();
