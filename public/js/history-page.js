(function () {
    const config = window.historyConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });

    const expenseCategories = config.expenseCategories || [];
    const incomeCategories = config.incomeCategories || [];

    let cohChart = null;
    let incomeExpenseChart = null;
    let months = [];
    let selectedMonth = config.latestMonth || '';
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

            input.addEventListener('input', updateTotals);

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

    function renderInputs() {
        const row = findMonth(selectedMonth);
        const monthInput = document.getElementById('historyMonth');
        const closingCohInput = document.getElementById('closingCohInput');

        if (monthInput) monthInput.value = selectedMonth;
        if (closingCohInput) closingCohInput.value = row?.closing_coh ?? '';

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

        const options = applySharedXAxisOptions(baseChartOptions(), 44);

        cohChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: months.map((row) => formatMonthLabel(row.month)),
                datasets: [
                    {
                        label: 'COH',
                        data: months.map((row) => row.closing_coh),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.12)',
                        borderWidth: 3,
                        pointRadius: 4,
                        tension: 0,
                        yAxisID: 'y',
                        spanGaps: true,
                    },
                ],
            },
            options,
            plugins: [
                createHistoryAxisLabelPlugin('historyCohLabels', (row) => [
                    { text: formatMonthLabel(row.month), color: '#212529' },
                    { text: money.format(toNumber(row.closing_coh, 0)), color: '#0d6efd' },
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

    function renderAll() {
        const latestDisplay = document.getElementById('latestMonthDisplay');
        if (latestDisplay && months.length) {
            latestDisplay.textContent = formatMonthLabel(months[months.length - 1].month);
        }

        renderInputs();
        renderCohChart();
        renderIncomeExpenseChart();
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

    async function saveMonth() {
        const monthInput = document.getElementById('historyMonth');
        const closingCohInput = document.getElementById('closingCohInput');
        const month = monthInput?.value || selectedMonth;
        const closingCoh = closingCohInput?.value;

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
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('#historyInputTabs [data-bs-title]').forEach((el) => {
                if (!bootstrap.Tooltip.getInstance(el)) {
                    new bootstrap.Tooltip(el, { trigger: 'hover focus' });
                }
            });
        }

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

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initMonthPicker();

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

        document.getElementById('saveHistoryBtn')?.addEventListener('click', () => {
            saveMonth().catch((error) => setStatus(error.message, true));
        });

        loadMonths(selectedMonth).catch((error) => setStatus(error.message, true));
    });
})();
