(function () {
    const projectionConfig = window.projectionConfig || {};
    const runEndpoint = projectionConfig.runEndpoint || '';
    const saveEndpoint = projectionConfig.saveEndpoint || '';
    const compareEndpoint = projectionConfig.compareEndpoint || '';
    const showScenarioBase = projectionConfig.showScenarioBase || '';
    const deleteScenarioBase = projectionConfig.deleteScenarioBase || '';
    const statutoryBrackets = projectionConfig.statutoryBrackets || { socso: [], eis: [] };
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const initialScenarios = projectionConfig.initialScenarios || [];
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
    const defaultBudgetProfiles = [
        { id: 'bcol', name: 'BCOL' },
        { id: 'fcol_lite', name: 'FCOL Lite' },
        { id: 'fcol_max', name: 'FCOL Max' },
    ];

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });
    let projectionChart = null;
    let currentProjectionMonths = [];
    let statusTimer = null;
    let loadedScenarioContext = { id: null, name: '' };
    let loadedScenarioSnapshot = '';
    let savedScenariosModal = null;
    let scenarioComparisonModal = null;
    let confirmActionModal = null;
    let salarySchedules = [];
    let editingSalaryScheduleId = null;
    let nextSalaryScheduleId = 1;
    let budgetProfiles = defaultBudgetProfiles.map((profile) => ({ ...profile, allocations: {} }));
    let selectedBudgetProfileId = 'bcol';

    function budgetLabel(budget) {
        return defaultBudgetProfiles.find((profile) => profile.id === budget)?.name
            || budgetProfiles.find((profile) => profile.id === budget)?.name
            || budget;
    }

    function toNumber(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function toMonthOrNull(value) {
        const normalized = (value || '').trim();
        if (!normalized.length) return null;
        if (/^\d{4}-\d{2}$/.test(normalized)) return normalized;
        if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) return normalized.slice(0, 7);
        return null;
    }

    function monthToDateValue(month) {
        if (!month) return '';
        return `${month}-01`;
    }

    function formatMonthLabel(month) {
        if (!month || month.length < 7) return month || '';
        const d = new Date(`${month}-01T00:00:00`);
        return Number.isNaN(d.getTime()) ? month : monthLabelFormatter.format(d);
    }

    function formatCompactMonthLabel(month) {
        return formatMonthLabel(month).replace(/\s+/, '-');
    }

    function setStatus(message, isError = false) {
        const el = document.getElementById('statusMessage');
        el.textContent = message;
        el.classList.toggle('is-error', isError);
        el.classList.add('is-visible');
        if (statusTimer) clearTimeout(statusTimer);
        statusTimer = setTimeout(() => {
            el.classList.remove('is-visible');
        }, 3200);
    }

    function formatToTwoDp(value) {
        const number = Number.parseFloat(value);
        return Number.isFinite(number) ? number.toFixed(2) : '0.00';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }

    function normalizeDecimalInputs(root = document) {
        root.querySelectorAll('input.money-input, input[type="number"][step="0.01"]').forEach((input) => {
            if (input.closest('#projectionRows')) return;
            input.value = formatToTwoDp(input.value);

            input.addEventListener('blur', () => {
                input.value = formatToTwoDp(input.value);
            });
        });
    }

    function normalizeCategoryToken(value) {
        const normalized = String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '_');
        let start = 0;
        let end = normalized.length;

        while (start < end && normalized[start] === '_') {
            start += 1;
        }

        while (end > start && normalized[end - 1] === '_') {
            end -= 1;
        }

        return normalized.slice(start, end);
    }

    function resolveCategoryKey(allocation) {
        const validCategoryIds = new Set(expenseCategories.map((category) => category.id));
        const normalizedId = normalizeCategoryToken(allocation?.category_id ?? '');
        if (normalizedId && normalizedId !== '0' && validCategoryIds.has(normalizedId)) {
            return normalizedId;
        }

        const normalizedName = normalizeCategoryToken(allocation?.name ?? '');
        if (normalizedName && validCategoryIds.has(normalizedName)) {
            return normalizedName;
        }

        const andNormalized = normalizedName.replace(/_and_/g, '_');
        if (andNormalized && validCategoryIds.has(andNormalized)) {
            return andNormalized;
        }

        return normalizedName;
    }

    function normalizeBudgetProfiles(cost = {}) {
        const budgets = cost.budgets || {};
        const profiles = Object.entries(budgets)
            .filter(([, budget]) => budget && typeof budget === 'object')
            .map(([id, budget]) => {
                const allocations = {};
                (budget.category_allocations || []).forEach((allocation) => {
                    allocations[resolveCategoryKey(allocation)] = toNumber(allocation.amount, 0);
                });

                return {
                    id,
                    name: String(budget.name || budgetLabel(id)).trim() || id,
                    allocations,
                };
            });

        return profiles.length ? profiles : defaultBudgetProfiles.map((profile) => ({ ...profile, allocations: {} }));
    }

    function selectedBudgetProfile() {
        return budgetProfiles.find((profile) => profile.id === selectedBudgetProfileId) || null;
    }

    function renderBudgetProfileControls() {
        const selectedProfileExists = budgetProfiles.some((profile) => profile.id === selectedBudgetProfileId);
        if (!selectedProfileExists) {
            selectedBudgetProfileId = '';
        }

        document.getElementById('budgetProfileName').value = selectedBudgetProfile()?.name || '';
        document.getElementById('saveBudgetProfileBtn').textContent = selectedBudgetProfileId ? 'Update' : 'Add';
    }

    function createCostAllocationRows(cost = null) {
        if (cost) {
            budgetProfiles = normalizeBudgetProfiles(cost);
            selectedBudgetProfileId = budgetProfiles[0]?.id || '';
        }

        const header = document.getElementById('costAllocationHeaderRows');
        const tbody = document.getElementById('costAllocationRows');
        header.innerHTML = '<th>Expense Category</th><th>Amount</th>';
        tbody.innerHTML = '';

        const profile = selectedBudgetProfile();
        expenseCategories.forEach((category) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${category.name}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">RM</span>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-col-category-id="${category.id}" value="${profile?.allocations?.[category.id] ?? 0}">
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

        const totalRow = document.createElement('tr');
        totalRow.className = 'table-light fw-semibold';
        totalRow.innerHTML = `
            <td>Total</td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">RM</span>
                    <input type="text" class="form-control form-control-sm" data-col-total value="0.00" readonly>
                </div>
            </td>
        `;
        tbody.appendChild(totalRow);

        renderBudgetProfileControls();
        renderBudgetPlanCards();
        normalizeDecimalInputs(tbody);
        attachBudgetAllocationListeners();
        updateBudgetTotalsSummary();
    }

    function createMonthlyBudgetRow(data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm month-input" data-col-month value="${data.month ?? ''}"></td>
            <td>
                <select class="form-select form-select-sm" data-col-budget>
                    ${budgetProfiles.map((profile) => `<option value="${escapeHtml(profile.id)}">${escapeHtml(profile.name)}</option>`).join('')}
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger">×</button></td>
        `;
        row.querySelector('[data-col-budget]').value = budgetProfiles.some((profile) => profile.id === data.budget)
            ? data.budget
            : (budgetProfiles[0]?.id || '');
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('monthlyBudgetRows').appendChild(row);
        initMonthPickers();
    }

    function monthSequence(startMonth, endMonth) {
        const start = toMonthOrNull(startMonth);
        const end = toMonthOrNull(endMonth);
        if (!start || !end) return [];

        const [startYear, startMon] = start.split('-').map(Number);
        const [endYear, endMon] = end.split('-').map(Number);
        const startDate = new Date(startYear, startMon - 1, 1);
        const endDate = new Date(endYear, endMon - 1, 1);
        if (endDate < startDate) return [];

        const months = [];
        for (
            let cursor = new Date(startDate.getTime());
            cursor <= endDate;
            cursor = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1)
        ) {
            const y = cursor.getFullYear();
            const m = String(cursor.getMonth() + 1).padStart(2, '0');
            months.push(`${y}-${m}`);
        }

        return months;
    }

    function syncMonthlyBudgetRows(preselected = []) {
        const months = monthSequence(
            document.getElementById('startMonth').value,
            document.getElementById('endMonth').value,
        );
        const selectedMap = new Map((preselected || []).map((item) => [item.month, item.budget]));
        const existingMap = new Map(
            Array.from(document.querySelectorAll('#monthlyBudgetRows tr'))
                .map((row) => [
                    toMonthOrNull(row.querySelector('[data-col-month]')?.value),
                    row.querySelector('[data-col-budget]')?.value || budgetProfiles[0]?.id || '',
                ])
                .filter(([month]) => Boolean(month)),
        );

        document.getElementById('monthlyBudgetRows').innerHTML = '';

        months.forEach((month) => {
            createMonthlyBudgetRow({
                month,
                budget: selectedMap.get(month) || existingMap.get(month) || budgetProfiles[0]?.id || '',
            });
        });
    }

    function collectCostOfLivingPayload() {
        const budgets = {};

        budgetProfiles.forEach((profile) => {
            budgets[profile.id] = {
                name: profile.name,
                category_allocations: expenseCategories.map((category) => ({
                    category_id: category.id,
                    name: category.name,
                    amount: toNumber(profile.allocations?.[category.id] ?? 0, 0),
                })),
            };
        });

        const monthlyBudgetSelection = Array.from(document.querySelectorAll('#monthlyBudgetRows tr')).map((row) => ({
            month: toMonthOrNull(row.querySelector('[data-col-month]').value),
            budget: row.querySelector('[data-col-budget]').value,
        })).filter((item) => item.month && budgetProfiles.some((profile) => profile.id === item.budget));

        return {
            budgets,
            monthly_budget_selection: monthlyBudgetSelection,
        };
    }

    function attachBudgetAllocationListeners() {
        document.querySelectorAll('[data-col-category-id]').forEach((input) => {
            input.addEventListener('blur', updateBudgetTotalsSummary);
            input.addEventListener('input', updateBudgetTotalsSummary);
        });
    }

    function updateBudgetTotalsSummary() {
        const total = Array.from(document.querySelectorAll('[data-col-category-id]'))
            .reduce((carry, input) => carry + toNumber(input.value, 0), 0);
        const totalInput = document.querySelector('[data-col-total]');
        if (totalInput) totalInput.value = money.format(total);
    }

    function saveVisibleBudgetProfileAllocations() {
        const profile = selectedBudgetProfile();
        if (!profile) return;

        profile.allocations = {};
        expenseCategories.forEach((category) => {
            const input = document.querySelector(`[data-col-category-id="${category.id}"]`);
            profile.allocations[category.id] = toNumber(input?.value ?? 0, 0);
        });
    }

    function updateMonthlyBudgetOptions() {
        document.querySelectorAll('[data-col-budget]').forEach((select) => {
            const previousValue = select.value;
            select.innerHTML = budgetProfiles
                .map((profile) => `<option value="${escapeHtml(profile.id)}">${escapeHtml(profile.name)}</option>`)
                .join('');
            select.value = budgetProfiles.some((profile) => profile.id === previousValue)
                ? previousValue
                : (budgetProfiles[0]?.id || '');
        });
    }

    function budgetProfileTotal(profile) {
        return expenseCategories.reduce((carry, category) => carry + toNumber(profile.allocations?.[category.id] ?? 0, 0), 0);
    }

    function renderBudgetPlanCards() {
        const container = document.getElementById('budgetPlanListCards');
        if (!container) return;

        container.innerHTML = '';
        if (!budgetProfiles.length) {
            container.innerHTML = '<div class="text-center text-secondary py-3">No budget plans added yet.</div>';
            return;
        }

        budgetProfiles.forEach((profile) => {
            const item = document.createElement('div');
            item.className = 'salary-schedule-list-item';
            const total = budgetProfileTotal(profile);
            const categoryCount = expenseCategories.filter((category) => toNumber(profile.allocations?.[category.id] ?? 0, 0) > 0).length;
            const categorySummary = `${categoryCount} categor${categoryCount === 1 ? 'y' : 'ies'} with assigned amounts`;
            const isEditing = profile.id === selectedBudgetProfileId;

            item.innerHTML = `
                <div class="salary-schedule-list-card">
                    <div class="salary-schedule-list-row">
                        <div>
                            <div class="salary-schedule-list-name">${escapeHtml(profile.name)}</div>
                            <div class="salary-schedule-list-description">${categorySummary}</div>
                        </div>
                        <div class="salary-schedule-list-right">
                            <div><strong>Total: RM ${money.format(total)}</strong></div>
                            <div>${isEditing ? 'Editing' : '&nbsp;'}</div>
                        </div>
                    </div>
                </div>
                <div class="salary-schedule-list-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-budget-plan-action="edit" data-budget-plan-id="${escapeHtml(profile.id)}" aria-label="Edit budget plan" title="Edit" data-bs-title="Edit" data-bs-placement="top">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-budget-plan-action="delete" data-budget-plan-id="${escapeHtml(profile.id)}" aria-label="Delete budget plan" title="Delete" data-bs-title="Delete" data-bs-placement="top">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                </div>
            `;
            container.appendChild(item);
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            container.querySelectorAll('[data-budget-plan-action]').forEach((btn) => {
                bootstrap.Tooltip.getOrCreateInstance(btn, { trigger: 'hover focus' });
            });
        }
    }

    function createBudgetProfileId(name) {
        const base = normalizeCategoryToken(name) || 'budget_profile';
        let candidate = base;
        let counter = 2;
        while (budgetProfiles.some((profile) => profile.id === candidate)) {
            candidate = `${base}_${counter}`;
            counter += 1;
        }

        return candidate;
    }

    function emptyBudgetAllocations() {
        return expenseCategories.reduce((allocations, category) => {
            allocations[category.id] = 0;
            return allocations;
        }, {});
    }

    function switchBudgetProfile(profileId) {
        selectedBudgetProfileId = profileId;
        createCostAllocationRows();
    }

    function saveBudgetProfileFromForm() {
        const nameInput = document.getElementById('budgetProfileName');
        const name = nameInput.value.trim();
        if (!name) {
            setStatus('Budget profile name is required.', true);
            return;
        }

        const profile = selectedBudgetProfile();
        if (!profile) {
            const id = createBudgetProfileId(name);
            budgetProfiles.push({ id, name, allocations: emptyBudgetAllocations() });
            selectedBudgetProfileId = id;
            saveVisibleBudgetProfileAllocations();
        } else {
            profile.name = name;
            saveVisibleBudgetProfileAllocations();
        }

        renderBudgetProfileControls();
        renderBudgetPlanCards();
        updateMonthlyBudgetOptions();
        setStatus(profile ? 'Budget plan updated.' : 'Budget plan added.');
    }

    function startNewBudgetProfile() {
        selectedBudgetProfileId = '';
        createCostAllocationRows();
        document.getElementById('budgetProfileName').focus();
        document.getElementById('budgetProfileName').select();
        setStatus('Budget plan form cleared.');
    }

    function deleteBudgetProfile(profileId) {
        if (budgetProfiles.length <= 1) {
            setStatus('At least one budget profile is required.', true);
            return;
        }

        const deletedId = profileId;
        budgetProfiles = budgetProfiles.filter((profile) => profile.id !== deletedId);
        if (selectedBudgetProfileId === deletedId) {
            selectedBudgetProfileId = budgetProfiles[0]?.id || '';
        }
        createCostAllocationRows();
        updateMonthlyBudgetOptions();
        setStatus('Budget plan deleted.');
    }

    function findStatutoryBracket(brackets, grossSalary) {
        if (!Array.isArray(brackets)) return null;

        for (const bracket of brackets) {
            if (!bracket || typeof bracket !== 'object') continue;
            const min = Number(bracket.min ?? 0);
            const max = bracket.max === null || bracket.max === undefined ? null : Number(bracket.max);
            if (grossSalary >= min && (max === null || grossSalary <= max)) {
                return bracket;
            }
        }

        return null;
    }

    function resolveStatutoryDeductions(grossSalary) {
        if (grossSalary <= 0) {
            return { employerSocso: 0, socso: 0, socsoL24: 0, eis: 0 };
        }

        const socsoBracket = findStatutoryBracket(statutoryBrackets.socso, grossSalary);
        const eisBracket = findStatutoryBracket(statutoryBrackets.eis, grossSalary);

        return {
            employerSocso: toNumber(socsoBracket?.employer_share ?? 0, 0),
            socso: toNumber(socsoBracket?.employee_INV ?? 0, 0),
            socsoL24: toNumber(socsoBracket?.employee_NEI ?? 0, 0),
            eis: toNumber(eisBracket?.employee ?? 0, 0),
        };
    }

    function updateEmploymentContributionSummary() {
        renderSalaryScheduleCards();
    }

    function initProjectionInputTabUI() {
        document.querySelectorAll('#projectionInputTabs [data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', () => {
                document.querySelectorAll('#projectionInputTabs .projection-input-tab').forEach((btn) => {
                    btn.classList.toggle('active', btn === tabButton);
                });
            });
        });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString('en-MY');
    }

    function showConfirmModal(message, confirmLabel = 'Confirm') {
        return new Promise((resolve) => {
            const body = document.getElementById('confirmActionModalBody');
            const okBtn = document.getElementById('confirmActionOkBtn');
            body.textContent = message;
            okBtn.textContent = confirmLabel;

            const clean = () => {
                okBtn.onclick = null;
                document.getElementById('confirmActionModal').removeEventListener('hidden.bs.modal', onHidden);
            };

            const onHidden = () => {
                clean();
                resolve(false);
            };

            okBtn.onclick = () => {
                clean();
                confirmActionModal.hide();
                resolve(true);
            };

            document.getElementById('confirmActionModal').addEventListener('hidden.bs.modal', onHidden, { once: true });
            confirmActionModal.show();
        });
    }

    function renderSavedScenariosRows() {
        const tbody = document.getElementById('savedScenariosRows');
        if (!initialScenarios.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-secondary py-3">No saved scenarios.</td></tr>';
            return;
        }

        tbody.innerHTML = initialScenarios.map((s) => {
            const notes = s.notes || '-';
            const notesTitle = s.notes ? ` title="${escapeHtml(s.notes)}"` : '';

            return `
                <tr>
                    <td>${escapeHtml(s.name)}</td>
                    <td class="saved-scenarios-notes-cell"${notesTitle}>${escapeHtml(notes)}</td>
                    <td>${formatDateTime(s.updated_at)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-action="load" data-scenario-id="${s.id}" title="Load">
                            <i class="fa-solid fa-folder-open"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" data-scenario-id="${s.id}" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function snapshotForLoadedScenarioComparison() {
        return JSON.stringify({
            payload: collectPayload(),
            notes: document.getElementById('saveNotes').value.trim(),
        });
    }

    function legacySalarySchedules(employment = {}) {
        if (!employment.salary_start_month) return [];

        const startMonth = toMonthOrNull(employment.salary_start_month);
        const probationMonths = Math.max(0, Math.trunc(toNumber(employment.probation_duration_months, 0)));
        if (!startMonth) return [];

        function addMonths(month, offset) {
            const [year, monthNumber] = month.split('-').map(Number);
            const date = new Date(year, monthNumber - 1 + offset, 1);
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        }

        const schedules = [];
        if (probationMonths > 0) {
            schedules.push({
                start_month: startMonth,
                end_month: addMonths(startMonth, probationMonths - 1),
                monthly_gross_salary: toNumber(employment.probation_salary, 0),
                employee_epf_rate_percent: toNumber(employment.employee_epf_rate_percent, 11),
                employer_epf_rate_percent: toNumber(employment.employer_epf_rate_percent, 13),
                note: 'Probation',
            });
        }

        schedules.push({
            start_month: addMonths(startMonth, probationMonths),
            end_month: '',
            monthly_gross_salary: toNumber(employment.confirmed_salary, 0),
            employee_epf_rate_percent: toNumber(employment.employee_epf_rate_percent, 11),
            employer_epf_rate_percent: toNumber(employment.employer_epf_rate_percent, 13),
            note: 'Confirmed',
        });

        return schedules;
    }

    function defaultSalarySchedules(startMonth) {
        return legacySalarySchedules({
            salary_start_month: startMonth,
            probation_duration_months: 3,
            probation_salary: 1800,
            confirmed_salary: 2200,
            employee_epf_rate_percent: 11,
            employer_epf_rate_percent: 13,
        });
    }

    function salaryScheduleDeductions(schedule) {
        const grossSalary = toNumber(schedule.monthly_gross_salary, 0);
        const employeeEpfRatePercent = toNumber(schedule.employee_epf_rate_percent, 0);
        const employerEpfRatePercent = toNumber(schedule.employer_epf_rate_percent, 0);
        const statutory = resolveStatutoryDeductions(grossSalary);
        const employeeEpf = grossSalary * (employeeEpfRatePercent / 100);
        const employerEpf = grossSalary * (employerEpfRatePercent / 100);

        return {
            grossSalary,
            employeeEpf,
            employerEpf,
            employerSocso: statutory.employerSocso,
            socso: statutory.socso,
            socsoL24: statutory.socsoL24,
            eis: statutory.eis,
            net: grossSalary - employeeEpf - statutory.socso - statutory.socsoL24 - statutory.eis,
        };
    }

    function normalizeSalarySchedule(schedule = {}) {
        return {
            id: schedule.id || `salary-schedule-${nextSalaryScheduleId++}`,
            start_month: toMonthOrNull(schedule.start_month) || '',
            end_month: toMonthOrNull(schedule.end_month) || '',
            note: String(schedule.note || '').trim(),
            monthly_gross_salary: toNumber(schedule.monthly_gross_salary, 0),
            employee_epf_rate_percent: toNumber(schedule.employee_epf_rate_percent ?? 11, 11),
            employer_epf_rate_percent: toNumber(schedule.employer_epf_rate_percent ?? 13, 13),
        };
    }

    function setSalaryScheduleFormMode(isEdit) {
        const btn = document.getElementById('saveSalaryScheduleBtn');
        const deleteBtn = document.getElementById('deleteSalaryScheduleBtn');
        if (btn) btn.textContent = isEdit ? 'Update' : 'Add';
        if (btn) btn.classList.toggle('w-100', !isEdit);
        if (deleteBtn) deleteBtn.classList.toggle('d-none', !isEdit);
    }

    function fillSalaryScheduleForm(schedule = null) {
        document.getElementById('salaryScheduleEditingId').value = schedule?.id || '';
        document.getElementById('salaryScheduleFrom').value = schedule?.start_month || toMonthOrNull(document.getElementById('startMonth').value) || '';
        document.getElementById('salaryScheduleUntil').value = schedule?.end_month || '';
        document.getElementById('salaryScheduleNote').value = schedule?.note || '';
        document.getElementById('salaryScheduleGross').value = formatToTwoDp(schedule?.monthly_gross_salary ?? 0);
        document.getElementById('employeeEpfRatePercent').value = formatToTwoDp(schedule?.employee_epf_rate_percent ?? 11);
        document.getElementById('employerEpfRatePercent').value = formatToTwoDp(schedule?.employer_epf_rate_percent ?? 13);
    }

    function renderSalaryScheduleCards() {
        const container = document.getElementById('salaryScheduleListCards');
        if (!container) return;

        container.innerHTML = '';
        if (!salarySchedules.length) {
            container.innerHTML = '<div class="text-center text-secondary py-3">No salary schedules added yet.</div>';
            return;
        }

        salarySchedules
            .slice()
            .sort((a, b) => String(a.start_month).localeCompare(String(b.start_month)))
            .forEach((schedule) => {
                const item = document.createElement('div');
                item.className = 'salary-schedule-list-item';
                const deductions = salaryScheduleDeductions(schedule);
                const startLabel = schedule.start_month ? formatCompactMonthLabel(schedule.start_month) : '-';
                const endLabel = schedule.end_month ? formatCompactMonthLabel(schedule.end_month) : 'Ongoing';
                const note = schedule.note || '&nbsp;';

                item.innerHTML = `
                    <button type="button" class="salary-schedule-list-card is-clickable ${editingSalaryScheduleId === schedule.id ? 'is-active' : ''}" data-salary-schedule-id="${schedule.id}" aria-label="Edit salary schedule from ${startLabel} to ${endLabel}">
                        <div class="salary-schedule-list-row">
                            <div>
                                <div class="salary-schedule-list-name">${startLabel} to ${endLabel}</div>
                                <div class="salary-schedule-list-description">${note}</div>
                            </div>
                            <div class="salary-schedule-list-right">
                                <div><strong>Gross: RM ${money.format(deductions.grossSalary)}</strong></div>
                                <div>Net: RM ${money.format(deductions.net)}</div>
                            </div>
                        </div>
                        <div class="salary-schedule-deduction-grid">
                            <div>Employee EPF<br><strong>RM ${money.format(deductions.employeeEpf)}</strong></div>
                            <div>Employer EPF<br><strong>RM ${money.format(deductions.employerEpf)}</strong></div>
                            <div>SOCSO<br><strong>RM ${money.format(deductions.socso)}</strong></div>
                            <div>SOCSO L24<br><strong>RM ${money.format(deductions.socsoL24)}</strong></div>
                            <div>EIS<br><strong>RM ${money.format(deductions.eis)}</strong></div>
                        </div>
                    </button>
                `;
                container.appendChild(item);
            });
    }

    function createBnplRow(data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm month-input" data-bnpl="month" value="${data.month ?? ''}"></td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">RM</span>
                    <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-bnpl="amount" value="${data.amount ?? 0}">
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm" data-bnpl="note" value="${data.note ?? ''}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger">×</button></td>
        `;
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('bnplRows').appendChild(row);
        initMonthPickers();
        normalizeDecimalInputs(row);
    }

    function createEventRow(data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm month-input" data-event="month" value="${data.month ?? ''}"></td>
            <td>
                <select class="form-select form-select-sm" data-event="type">
                    <option value="allowance">Allowance</option>
                    <option value="household">Household Contribution</option>
                    <option value="one_off_income">One-off Income</option>
                    <option value="one_off_expense">One-off Expense</option>
                    <option value="elr_override">ELR Override</option>
                </select>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">RM</span>
                    <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-event="amount" value="${data.amount ?? 0}">
                </div>
            </td>
            <td><input type="text" class="form-control form-control-sm" data-event="note" value="${data.note ?? ''}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger">×</button></td>
        `;
        row.querySelector('[data-event="type"]').value = data.type ?? 'one_off_expense';
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('eventRows').appendChild(row);
        initMonthPickers();
        normalizeDecimalInputs(row);
    }

    function createElrScheduleRow(data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" class="form-control form-control-sm month-input" data-elr-schedule="start_month" value="${data.start_month ?? ''}"></td>
            <td><input type="text" class="form-control form-control-sm month-input" data-elr-schedule="end_month" value="${data.end_month ?? ''}"></td>
            <td>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">RM</span>
                    <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-elr-schedule="amount" value="${data.amount ?? 0}">
                </div>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger">×</button></td>
        `;
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('elrScheduleRows').appendChild(row);
        initMonthPickers();
        normalizeDecimalInputs(row);
    }

    function collectPayload() {
        const bnpl = Array.from(document.querySelectorAll('#bnplRows tr')).map((row) => ({
            month: toMonthOrNull(row.querySelector('[data-bnpl="month"]').value),
            amount: toNumber(row.querySelector('[data-bnpl="amount"]').value, 0),
            note: row.querySelector('[data-bnpl="note"]').value.trim(),
        })).filter((item) => item.month);

        const events = Array.from(document.querySelectorAll('#eventRows tr')).map((row) => ({
            month: toMonthOrNull(row.querySelector('[data-event="month"]').value),
            type: row.querySelector('[data-event="type"]').value,
            amount: toNumber(row.querySelector('[data-event="amount"]').value, 0),
            note: row.querySelector('[data-event="note"]').value.trim(),
        })).filter((item) => item.month);

        const schedules = Array.from(document.querySelectorAll('#elrScheduleRows tr')).map((row) => ({
            start_month: toMonthOrNull(row.querySelector('[data-elr-schedule="start_month"]').value),
            end_month: toMonthOrNull(row.querySelector('[data-elr-schedule="end_month"]').value),
            amount: toNumber(row.querySelector('[data-elr-schedule="amount"]').value, 0),
        })).filter((item) => item.start_month && item.end_month);

        return {
            scenario: {
                start_month: toMonthOrNull(document.getElementById('startMonth').value),
                end_month: toMonthOrNull(document.getElementById('endMonth').value),
                starting_coh: toNumber(document.getElementById('startingCoh').value, 0),
                starting_elr: toNumber(document.getElementById('startingElr').value, 0),
                starting_epf: toNumber(document.getElementById('startingEpf').value, 0),
            },
            employment: {
                salary_schedules: salarySchedules.map((schedule) => ({
                    start_month: schedule.start_month,
                    end_month: schedule.end_month || null,
                    monthly_gross_salary: toNumber(schedule.monthly_gross_salary, 0),
                    employee_epf_rate_percent: toNumber(schedule.employee_epf_rate_percent, 0),
                    employer_epf_rate_percent: toNumber(schedule.employer_epf_rate_percent, 0),
                    note: schedule.note || '',
                })).filter((schedule) => schedule.start_month),
                salary_paid_in_arrears: document.getElementById('salaryPaidInArrears').checked,
            },
            cost_of_living: {
                ...collectCostOfLivingPayload(),
            },
            ptptn: {
                waiver_granted: document.getElementById('ptptnWaiverGranted').checked,
                monthly_repayment: toNumber(document.getElementById('ptptnMonthlyRepayment').value, 0),
                repayment_start_month: toMonthOrNull(document.getElementById('ptptnRepaymentStartMonth').value),
            },
            bnpl,
            events,
            elr: {
                schedules,
                note: document.getElementById('elrNote').value.trim(),
                compound_interest_enabled: document.getElementById('elrCompoundInterestEnabled').checked,
                annual_interest_rate_percent: toNumber(document.getElementById('elrAnnualInterestRatePercent').value, 0),
            },
            epf: {
                employee_rate_percent: toNumber(document.getElementById('employeeEpfRatePercent').value, 0),
                employer_rate_percent: toNumber(document.getElementById('employerEpfRatePercent').value, 0),
            },
        };
    }

    function applyPayload(payload) {
        const scenario = payload.scenario || {};
        const employment = payload.employment || {};
        const cost = payload.cost_of_living || {};
        const ptptn = payload.ptptn || {};
        const elr = payload.elr || {};
        const epf = payload.epf || {};

        document.getElementById('startMonth').value = scenario.start_month || '';
        document.getElementById('endMonth').value = scenario.end_month || '';
        document.getElementById('startingCoh').value = scenario.starting_coh ?? 0;
        document.getElementById('startingElr').value = scenario.starting_elr ?? 0;
        document.getElementById('startingEpf').value = scenario.starting_epf ?? 0;

        salarySchedules = (employment.salary_schedules || legacySalarySchedules(employment)).map(normalizeSalarySchedule);
        editingSalaryScheduleId = null;
        setSalaryScheduleFormMode(false);
        fillSalaryScheduleForm(null);
        renderSalaryScheduleCards();
        document.getElementById('salaryPaidInArrears').checked = Boolean(employment.salary_paid_in_arrears);
        updateEmploymentContributionSummary();

        const legacyCost = {
            budgets: {
                bcol: { category_allocations: [] },
                fcol_lite: { category_allocations: [] },
                fcol_max: { category_allocations: [] },
            },
            monthly_budget_selection: [],
        };
        if (cost.bcol_amount !== undefined || cost.fcol_lite_amount !== undefined || cost.fcol_max_amount !== undefined) {
            const fallbackCategory = expenseCategories.find((item) => item.name === 'Others') || expenseCategories[0];
            if (fallbackCategory) {
                legacyCost.budgets.bcol.category_allocations.push({
                    category_id: fallbackCategory.id,
                    name: fallbackCategory.name,
                    amount: toNumber(cost.bcol_amount, 0),
                });
                legacyCost.budgets.fcol_lite.category_allocations.push({
                    category_id: fallbackCategory.id,
                    name: fallbackCategory.name,
                    amount: toNumber(cost.fcol_lite_amount, 0),
                });
                legacyCost.budgets.fcol_max.category_allocations.push({
                    category_id: fallbackCategory.id,
                    name: fallbackCategory.name,
                    amount: toNumber(cost.fcol_max_amount, 0),
                });
            }
        }
        createCostAllocationRows(cost.budgets ? cost : legacyCost);
        syncMonthlyBudgetRows(cost.monthly_budget_selection || []);

        document.getElementById('ptptnWaiverGranted').checked = Boolean(ptptn.waiver_granted);
        document.getElementById('ptptnMonthlyRepayment').value = ptptn.monthly_repayment ?? 0;
        document.getElementById('ptptnRepaymentStartMonth').value = ptptn.repayment_start_month || '';

        document.getElementById('employeeEpfRatePercent').value = epf.employee_rate_percent ?? 0;
        document.getElementById('employerEpfRatePercent').value = epf.employer_rate_percent ?? 0;

        document.getElementById('bnplRows').innerHTML = '';
        (payload.bnpl || []).forEach(createBnplRow);
        if (!payload.bnpl || payload.bnpl.length === 0) {
            createBnplRow({ month: scenario.start_month || '', amount: 0, note: '' });
        }

        document.getElementById('eventRows').innerHTML = '';
        (payload.events || []).forEach(createEventRow);

        document.getElementById('elrScheduleRows').innerHTML = '';
        (elr.schedules || []).forEach(createElrScheduleRow);
        document.getElementById('elrNote').value = elr.note || '';
        document.getElementById('elrCompoundInterestEnabled').checked = Boolean(elr.compound_interest_enabled);
        document.getElementById('elrAnnualInterestRatePercent').value = elr.annual_interest_rate_percent ?? 0;
        initMonthPickers();
    }

    function renderProjection(result) {
        const months = result.months || [];
        currentProjectionMonths = months;
        const summary = result.summary || {};

        const finalCoh = toNumber(summary.final_coh, 0);
        const finalElr = toNumber(summary.final_elr, 0);
        const finalEpf = toNumber(summary.final_epf, 0);
        const finalTfp = toNumber(summary.final_tfp, finalCoh + finalElr + finalEpf);
        document.getElementById('summaryFinalCoh').textContent = `RM ${money.format(finalCoh)}`;
        document.getElementById('summaryFinalElr').textContent = `RM ${money.format(finalElr)}`;
        document.getElementById('summaryFinalEpf').textContent = `RM ${money.format(finalEpf)}`;
        document.getElementById('summaryFinalTfp').textContent = `RM ${money.format(finalTfp)}`;
        document.getElementById('summaryFinalCoh').classList.toggle('negative-value', finalCoh < 0);
        document.getElementById('summaryFinalElr').classList.toggle('negative-value', finalElr < 0);
        document.getElementById('summaryFinalEpf').classList.toggle('negative-value', finalEpf < 0);
        document.getElementById('summaryFinalTfp').classList.toggle('negative-value', finalTfp < 0);

        const tbody = document.getElementById('projectionRows');
        tbody.innerHTML = '';

        if (!months.length) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">No projection rows returned.</td></tr>';
            return;
        }

        for (const row of months) {
            const tr = document.createElement('tr');
            const openingCoh = toNumber(row.opening_coh, 0);
            const netIncome = toNumber(row.net_income, 0);
            const expenses = toNumber(row.expenses, 0);
            const debtServicing = toNumber(row.debt_servicing, 0);
            const closingCoh = toNumber(row.closing_coh, 0);
            const closingElr = toNumber(row.closing_elr, 0);
            const closingEpf = toNumber(row.closing_epf, 0);
            const tfp = closingCoh + closingElr + closingEpf;
            tr.innerHTML = `
                <td>${formatMonthLabel(row.month)}</td>
                <td class="text-end ${openingCoh < 0 ? 'negative-value' : ''}">${money.format(openingCoh)}</td>
                <td class="text-end ${netIncome < 0 ? 'negative-value' : ''}">${money.format(netIncome)}</td>
                <td class="text-end ${expenses < 0 ? 'negative-value' : ''}">${money.format(expenses)}</td>
                <td class="text-end ${debtServicing < 0 ? 'negative-value' : ''}">${money.format(debtServicing)}</td>
                <td class="text-end ${closingCoh < 0 ? 'negative-value' : ''}">${money.format(closingCoh)}</td>
                <td class="text-end ${closingElr < 0 ? 'negative-value' : ''}">${money.format(closingElr)}</td>
                <td class="text-end ${closingEpf < 0 ? 'negative-value' : ''}">${money.format(closingEpf)}</td>
                <td class="text-end ${tfp < 0 ? 'negative-value' : ''}">${money.format(tfp)}</td>
            `;
            tbody.appendChild(tr);
        }

        renderProjectionChart(months);
    }

    function renderProjectionChart(months) {
        const ctx = document.getElementById('projectionStackedChart');
        if (!ctx) return;
        const selectedType = document.getElementById('chartType')?.value || 'balance_lines';
        const isStackedBar = selectedType === 'balance_stack';
        const isTfpLine = selectedType === 'tfp_line';

        const labels = months.map((row) => formatMonthLabel(row.month));
        const coh = months.map((row) => toNumber(row.closing_coh, 0));
        const elr = months.map((row) => toNumber(row.closing_elr, 0));
        const epf = months.map((row) => toNumber(row.closing_epf, 0));
        const tfp = months.map((row) => (
            toNumber(row.closing_coh, 0)
            + toNumber(row.closing_elr, 0)
            + toNumber(row.closing_epf, 0)
        ));

        if (projectionChart) {
            projectionChart.destroy();
        }

        const balanceDatasets = [
            {
                label: 'Closing COH',
                data: coh,
                borderColor: '#495057',
                backgroundColor: isStackedBar ? '#495057' : 'rgba(73,80,87,0.15)',
                pointRadius: isStackedBar ? 0 : 2,
                pointHoverRadius: isStackedBar ? 0 : 4,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
            },
            {
                label: 'ELR',
                data: elr,
                borderColor: '#198754',
                backgroundColor: isStackedBar ? '#198754' : 'rgba(25,135,84,0.15)',
                pointRadius: isStackedBar ? 0 : 2,
                pointHoverRadius: isStackedBar ? 0 : 4,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
            },
            {
                label: 'EPF',
                data: epf,
                borderColor: '#0d6efd',
                backgroundColor: isStackedBar ? '#0d6efd' : 'rgba(13,110,253,0.15)',
                pointRadius: isStackedBar ? 0 : 2,
                pointHoverRadius: isStackedBar ? 0 : 4,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
            },
        ];
        const tfpDatasets = [
            {
                label: 'TFP',
                data: tfp,
                borderColor: '#6f42c1',
                backgroundColor: 'rgba(111,66,193,0.12)',
                pointRadius: 2,
                pointHoverRadius: 4,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
            },
        ];

        projectionChart = new Chart(ctx, {
            type: isStackedBar ? 'bar' : 'line',
            data: {
                labels,
                datasets: isTfpLine ? tfpDatasets : balanceDatasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: isStackedBar },
                    y: { stacked: isStackedBar },
                },
                plugins: {
                    legend: { position: 'top' },
                },
            },
        });
    }

    function renderComparison(comparisons) {
        const tbody = document.getElementById('comparisonRows');
        tbody.innerHTML = '';

        if (!comparisons || comparisons.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-3">No comparison data returned.</td></tr>';
            return;
        }

        comparisons.forEach((item) => {
            const summary = item.result?.summary || {};
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.scenario.name}</td>
                <td class="text-end ${summary.final_coh < 0 ? 'negative-value' : ''}">${money.format(summary.final_coh || 0)}</td>
                <td class="text-end ${summary.final_elr < 0 ? 'negative-value' : ''}">${money.format(summary.final_elr || 0)}</td>
                <td class="text-end ${summary.final_epf < 0 ? 'negative-value' : ''}">${money.format(summary.final_epf || 0)}</td>
                <td class="text-end ${summary.lowest_coh < 0 ? 'negative-value' : ''}">${money.format(summary.lowest_coh || 0)}</td>
                <td class="text-end ${summary.highest_coh < 0 ? 'negative-value' : ''}">${money.format(summary.highest_coh || 0)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const msg = data?.message || Object.values(data?.errors || {}).flat().join(' ') || 'Request failed.';
            throw new Error(msg);
        }

        return data;
    }

    function populateScenarioSelects(scenarios) {
        const selects = [
            document.getElementById('compareScenarioA'),
            document.getElementById('compareScenarioB'),
        ];

        selects.forEach((select) => {
            const current = select.value;
            select.innerHTML = '<option value="">Select...</option>';
            scenarios.forEach((scenario) => {
                const option = document.createElement('option');
                option.value = String(scenario.id);
                option.textContent = scenario.name;
                select.appendChild(option);
            });
            if (current) {
                select.value = current;
            }
        });
    }

    function addScenarioOption(scenario) {
        const idx = initialScenarios.findIndex((s) => String(s.id) === String(scenario.id));
        if (idx >= 0) {
            initialScenarios[idx] = scenario;
        } else {
            initialScenarios.unshift(scenario);
        }
        initialScenarios.sort((a, b) => String(b.updated_at || '').localeCompare(String(a.updated_at || '')));
        populateScenarioSelects(initialScenarios);
        renderSavedScenariosRows();
    }

    function removeScenarioOption(scenarioId) {
        const idx = initialScenarios.findIndex((s) => String(s.id) === String(scenarioId));
        if (idx >= 0) {
            initialScenarios.splice(idx, 1);
        }
        populateScenarioSelects(initialScenarios);
        renderSavedScenariosRows();
    }

    async function loadScenarioById(scenarioId) {
        setStatus('Loading scenario...');

        const response = await fetch(`${showScenarioBase}/${scenarioId}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data?.message || 'Unable to load scenario.');
        }

        applyPayload(data.scenario.parameters_json || {});
        renderProjection(data.result || {});
        document.getElementById('saveName').value = data.scenario.name || '';
        document.getElementById('saveNotes').value = data.scenario.notes || '';
        loadedScenarioContext = { id: data.scenario.id ?? null, name: data.scenario.name || '' };
        loadedScenarioSnapshot = snapshotForLoadedScenarioComparison();
        setStatus('Scenario loaded.');
    }

    async function deleteScenarioById(scenarioId) {
        const response = await fetch(`${deleteScenarioBase}/${scenarioId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data?.message || 'Unable to delete scenario.');
        }
        return data;
    }

    function initMonthPickers() {
        document.querySelectorAll('.month-input').forEach((input) => {
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
        });
    }

    function resetProjectionOutputs() {
        document.getElementById('summaryFinalCoh').textContent = 'RM 0.00';
        document.getElementById('summaryFinalElr').textContent = 'RM 0.00';
        document.getElementById('summaryFinalEpf').textContent = 'RM 0.00';
        document.getElementById('summaryFinalTfp').textContent = 'RM 0.00';
        document.getElementById('summaryFinalCoh').classList.remove('negative-value');
        document.getElementById('summaryFinalElr').classList.remove('negative-value');
        document.getElementById('summaryFinalEpf').classList.remove('negative-value');
        document.getElementById('summaryFinalTfp').classList.remove('negative-value');
        document.getElementById('projectionRows').innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">Run a projection to view results.</td></tr>';
        currentProjectionMonths = [];
        if (projectionChart) {
            projectionChart.destroy();
            projectionChart = null;
        }
    }

    function clearAllInputsAndSelections() {
        const startMonth = toMonthOrNull(document.getElementById('startMonth').defaultValue) || toMonthOrNull(document.getElementById('startMonth').value);
        const endMonth = toMonthOrNull(document.getElementById('endMonth').defaultValue) || toMonthOrNull(document.getElementById('endMonth').value);

        document.getElementById('saveName').value = '';
        document.getElementById('saveNotes').value = '';

        document.getElementById('startMonth').value = startMonth || '';
        document.getElementById('endMonth').value = endMonth || '';
        document.getElementById('startingCoh').value = '0.00';
        document.getElementById('startingElr').value = '0.00';
        document.getElementById('startingEpf').value = '0.00';

        salarySchedules = defaultSalarySchedules(startMonth).map(normalizeSalarySchedule);
        editingSalaryScheduleId = null;
        setSalaryScheduleFormMode(false);
        fillSalaryScheduleForm(null);
        renderSalaryScheduleCards();
        document.getElementById('salaryPaidInArrears').checked = true;

        createCostAllocationRows();
        syncMonthlyBudgetRows();

        document.getElementById('ptptnWaiverGranted').checked = false;
        document.getElementById('ptptnMonthlyRepayment').value = '120.00';
        document.getElementById('ptptnRepaymentStartMonth').value = '';

        document.getElementById('employeeEpfRatePercent').value = '11.00';
        document.getElementById('employerEpfRatePercent').value = '13.00';

        document.getElementById('bnplRows').innerHTML = '';
        createBnplRow({ month: startMonth || '', amount: 0, note: '' });

        document.getElementById('eventRows').innerHTML = '';
        createEventRow({ month: startMonth || '', type: 'one_off_expense', amount: 0, note: '' });

        document.getElementById('elrScheduleRows').innerHTML = '';
        document.getElementById('elrNote').value = '';
        document.getElementById('elrCompoundInterestEnabled').checked = false;
        document.getElementById('elrAnnualInterestRatePercent').value = '0.00';

        document.getElementById('compareScenarioA').value = '';
        document.getElementById('compareScenarioB').value = '';
        document.getElementById('comparisonRows').innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-3">No comparison data yet.</td></tr>';

        initMonthPickers();
        normalizeDecimalInputs();
        updateEmploymentContributionSummary();
        resetProjectionOutputs();

        loadedScenarioContext = { id: null, name: '' };
        loadedScenarioSnapshot = '';
    }

    document.getElementById('addBnplBtn').addEventListener('click', () => createBnplRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        amount: 0,
        note: '',
    }));

    document.getElementById('addEventBtn').addEventListener('click', () => createEventRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        type: 'one_off_expense',
        amount: 0,
        note: '',
    }));

    document.getElementById('addElrScheduleBtn').addEventListener('click', () => createElrScheduleRow({
        start_month: toMonthOrNull(document.getElementById('startMonth').value),
        end_month: toMonthOrNull(document.getElementById('endMonth').value),
        amount: 0,
    }));

    document.getElementById('saveSalaryScheduleBtn').addEventListener('click', () => {
        const schedule = normalizeSalarySchedule({
            id: editingSalaryScheduleId,
            start_month: document.getElementById('salaryScheduleFrom').value,
            end_month: document.getElementById('salaryScheduleUntil').value,
            note: document.getElementById('salaryScheduleNote').value,
            monthly_gross_salary: document.getElementById('salaryScheduleGross').value,
            employee_epf_rate_percent: document.getElementById('employeeEpfRatePercent').value,
            employer_epf_rate_percent: document.getElementById('employerEpfRatePercent').value,
        });

        if (!schedule.start_month) {
            setStatus('Salary schedule From month is required.', true);
            return;
        }

        if (editingSalaryScheduleId) {
            salarySchedules = salarySchedules.map((item) => item.id === editingSalaryScheduleId ? schedule : item);
            editingSalaryScheduleId = null;
            setSalaryScheduleFormMode(false);
            setStatus('Salary schedule updated.');
        } else {
            salarySchedules.push(schedule);
            setStatus('Salary schedule added.');
        }

        fillSalaryScheduleForm(null);
        renderSalaryScheduleCards();
    });

    document.getElementById('deleteSalaryScheduleBtn').addEventListener('click', () => {
        if (!editingSalaryScheduleId) return;

        salarySchedules = salarySchedules.filter((item) => item.id !== editingSalaryScheduleId);
        editingSalaryScheduleId = null;
        setSalaryScheduleFormMode(false);
        fillSalaryScheduleForm(null);
        renderSalaryScheduleCards();
        setStatus('Salary schedule deleted.');
    });

    document.getElementById('salaryScheduleListCards').addEventListener('click', (event) => {
        const card = event.target.closest('.salary-schedule-list-card[data-salary-schedule-id]');
        if (!card) return;

        const scheduleId = card.dataset.salaryScheduleId;
        const schedule = salarySchedules.find((item) => item.id === scheduleId);
        if (!schedule) return;

        editingSalaryScheduleId = scheduleId;
        fillSalaryScheduleForm(schedule);
        setSalaryScheduleFormMode(true);
        renderSalaryScheduleCards();
        setStatus('Editing salary schedule.');
    });

    document.getElementById('saveBudgetProfileBtn').addEventListener('click', saveBudgetProfileFromForm);
    document.getElementById('newBudgetProfileBtn').addEventListener('click', startNewBudgetProfile);
    document.getElementById('budgetPlanListCards').addEventListener('click', (event) => {
        const button = event.target.closest('button[data-budget-plan-action]');
        if (!button) return;

        const action = button.dataset.budgetPlanAction;
        const profileId = button.dataset.budgetPlanId;
        const profile = budgetProfiles.find((item) => item.id === profileId);
        if (!profile) return;

        if (action === 'edit') {
            switchBudgetProfile(profileId);
            setStatus('Editing budget plan.');
            return;
        }

        if (action === 'delete') {
            deleteBudgetProfile(profileId);
        }
    });

    document.getElementById('startMonth').addEventListener('change', () => syncMonthlyBudgetRows());
    document.getElementById('endMonth').addEventListener('change', () => syncMonthlyBudgetRows());
    document.getElementById('employeeEpfRatePercent').addEventListener('input', updateEmploymentContributionSummary);
    document.getElementById('employeeEpfRatePercent').addEventListener('blur', updateEmploymentContributionSummary);
    document.getElementById('employerEpfRatePercent').addEventListener('input', updateEmploymentContributionSummary);
    document.getElementById('employerEpfRatePercent').addEventListener('blur', updateEmploymentContributionSummary);

    document.getElementById('runProjectionBtn').addEventListener('click', async () => {
        setStatus('Running projection...');

        try {
            const payload = collectPayload();
            const result = await postJson(runEndpoint, payload);
            renderProjection(result);
            setStatus('Projection completed.');
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    document.getElementById('saveScenarioBtn').addEventListener('click', async () => {
        const name = document.getElementById('saveName').value.trim();

        if (!name) {
            setStatus('Scenario name is required before saving.', true);
            return;
        }

        const isEditingLoadedScenario = loadedScenarioContext.id !== null && name === loadedScenarioContext.name;
        if (isEditingLoadedScenario) {
            const currentSnapshot = snapshotForLoadedScenarioComparison();
            if (currentSnapshot !== loadedScenarioSnapshot) {
                const confirmed = await showConfirmModal(`Save changes to scenario "${loadedScenarioContext.name}"?`, 'Save');
                if (!confirmed) {
                    setStatus('Save cancelled.');
                    return;
                }
            }
        }

        setStatus('Saving scenario...');

        try {
            const payload = collectPayload();
            const requestBody = {
                name,
                notes: document.getElementById('saveNotes').value.trim(),
                ...payload,
            };
            if (isEditingLoadedScenario && loadedScenarioContext.id !== null) {
                requestBody.scenario_id = loadedScenarioContext.id;
            }
            const response = await postJson(saveEndpoint, {
                ...requestBody,
            });

            renderProjection(response.result);
            addScenarioOption(response.scenario);
            loadedScenarioContext = { id: response.scenario.id, name: response.scenario.name };
            loadedScenarioSnapshot = snapshotForLoadedScenarioComparison();
            setStatus(response.message || 'Scenario saved.');
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    document.getElementById('clearInputsBtn').addEventListener('click', () => {
        clearAllInputsAndSelections();
        setStatus('All inputs and selections cleared.');
    });

    document.getElementById('openScenariosBtn').addEventListener('click', () => {
        renderSavedScenariosRows();
        savedScenariosModal.show();
    });

    document.getElementById('savedScenariosRows').addEventListener('click', async (event) => {
        const btn = event.target.closest('button[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        const scenarioId = btn.dataset.scenarioId;
        const scenario = initialScenarios.find((s) => String(s.id) === String(scenarioId));
        if (!scenario) return;

        if (action === 'load') {
            try {
                await loadScenarioById(scenarioId);
                savedScenariosModal.hide();
            } catch (error) {
                setStatus(error.message, true);
            }
            return;
        }

        if (action === 'delete') {
            const confirmed = await showConfirmModal(`Delete scenario "${scenario.name}"? This cannot be undone.`, 'Delete');
            if (!confirmed) return;

            try {
                await deleteScenarioById(scenarioId);
                removeScenarioOption(scenarioId);
                if (String(loadedScenarioContext.id) === String(scenarioId)) {
                    loadedScenarioContext = { id: null, name: '' };
                    loadedScenarioSnapshot = '';
                }
                setStatus('Scenario deleted.');
            } catch (error) {
                setStatus(error.message, true);
            }
        }
    });

    document.getElementById('compareScenariosBtn').addEventListener('click', async () => {
        const a = document.getElementById('compareScenarioA').value;
        const b = document.getElementById('compareScenarioB').value;

        if (!a || !b) {
            setStatus('Choose two scenarios to compare.', true);
            return;
        }

        if (a === b) {
            setStatus('Select two different scenarios.', true);
            return;
        }

        setStatus('Comparing scenarios...');

        try {
            const data = await postJson(compareEndpoint, {
                scenario_ids: [Number(a), Number(b)],
            });

            renderComparison(data.comparisons || []);
            scenarioComparisonModal.show();
            setStatus('Comparison completed.');
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    document.getElementById('chartType').addEventListener('change', () => {
        if (currentProjectionMonths.length > 0) {
            renderProjectionChart(currentProjectionMonths);
        }
    });

    savedScenariosModal = new bootstrap.Modal(document.getElementById('savedScenariosModal'));
    scenarioComparisonModal = new bootstrap.Modal(document.getElementById('scenarioComparisonModal'));
    confirmActionModal = new bootstrap.Modal(document.getElementById('confirmActionModal'));

    populateScenarioSelects(initialScenarios);
    renderSavedScenariosRows();
    initProjectionInputTabUI();
    createCostAllocationRows();
    initMonthPickers();
    normalizeDecimalInputs();
    syncMonthlyBudgetRows();
    salarySchedules = defaultSalarySchedules(toMonthOrNull(document.getElementById('startMonth').value)).map(normalizeSalarySchedule);
    fillSalaryScheduleForm(null);
    renderSalaryScheduleCards();
    updateEmploymentContributionSummary();

    createBnplRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        amount: 0,
        note: '',
    });

    createEventRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        type: 'one_off_expense',
        amount: 0,
        note: '',
    });
})();
