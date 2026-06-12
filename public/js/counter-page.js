(() => {
    const config = window.counterPageConfig || {};
    const snapshot = config.snapshot || {};

    /* DOM refs */
    const actualCounterElement = document.getElementById('actualCounterValue');
    const expectedCounterElement = document.getElementById('expectedCounterValue');
    const fabBtn = document.getElementById('fabBtn');
    const backdrop = document.getElementById('popupBackdrop');
    const tabSelector = document.getElementById('tabSelector');
    const contentPopup = document.getElementById('contentPopup');
    const popupTitle = document.getElementById('popupTitle');
    const btnBack = document.getElementById('btnBack');
    const btnClosePopup = document.getElementById('btnClosePopup');
    const panelCalendar = document.getElementById('panel-calendar');
    const panelSchedules = document.getElementById('panel-schedules');
    const panelSettings = document.getElementById('panel-settings');
    const requiredElements = [
        fabBtn,
        backdrop,
        tabSelector,
        contentPopup,
        popupTitle,
        btnBack,
        btnClosePopup,
        panelCalendar,
        panelSchedules,
        panelSettings,
    ];

    let currentTab = null;

    /* state */
    let actualValue = Number(snapshot.actual_counter ?? snapshot.counter ?? 0);
    let expectedValue = Number(snapshot.expected_counter ?? snapshot.counter ?? 0);
    let accruedSalaryValue = Number(snapshot.accrued_salary || 0);
    let incrementPerSecond = Number(snapshot.increment_per_second || 0);

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function renderCounter() {
        if (actualCounterElement) {
            actualCounterElement.textContent = `RM ${formatter.format(actualValue)}`;
        }

        if (expectedCounterElement) {
            expectedCounterElement.textContent = `RM ${formatter.format(expectedValue)}`;
        }
    }

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

    function updateIncrementStatus() {
        const statusEl = document.getElementById('incrementStatus');
        if (!statusEl) {
            return;
        }

        if (incrementPerSecond > 0) {
            statusEl.textContent = 'INCREMENTING (GET TO WORK!)';
            statusEl.style.color = '#6c757d';
        } else {
            statusEl.textContent = 'PAUSED INCREMENT (RELAX!)';
            statusEl.style.color = '#6c757d';
        }
    }

    function tick() {
        expectedValue += incrementPerSecond;
        accruedSalaryValue += incrementPerSecond;
        renderCounter();
        renderAccruedSalary();
        renderDynamicTotal();
        updateIncrementStatus();
    }

    async function syncSnapshot() {
        if (!config.snapshotUrl) {
            return;
        }

        const response = await fetch(config.snapshotUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        actualValue = Number(data.actual_counter ?? data.counter ?? 0);
        expectedValue = Number(data.expected_counter ?? data.counter ?? 0);
        accruedSalaryValue = Number(data.accrued_salary);
        incrementPerSecond = Number(data.increment_per_second);
        renderCounter();
        renderAccruedSalary();
        renderDynamicTotal();
        updateIncrementStatus();
    }

    function openSelector() {
        tabSelector.classList.add('show');
        contentPopup.classList.remove('show');
        backdrop.classList.add('show');
        fabBtn.classList.add('open');
        currentTab = null;
    }

    function openTab(tab) {
        currentTab = tab;
        tabSelector.classList.remove('show');
        contentPopup.classList.add('show');

        panelCalendar.classList.add('d-none');
        panelSchedules.classList.add('d-none');
        panelSettings.classList.add('d-none');

        if (tab === 'calendar') {
            panelCalendar.classList.remove('d-none');
            popupTitle.textContent = 'Workday Calendar';
            window.dispatchEvent(new Event('resize'));
            return;
        }

        if (tab === 'schedules') {
            panelSchedules.classList.remove('d-none');
            popupTitle.textContent = 'Salary Schedules';
            return;
        }

        if (tab === 'settings') {
            panelSettings.classList.remove('d-none');
            popupTitle.textContent = 'Settings';
        }
    }

    function closePopup() {
        tabSelector.classList.remove('show');
        contentPopup.classList.remove('show');
        backdrop.classList.remove('show');
        fabBtn.classList.remove('open');
        currentTab = null;
    }

    function backToSelector() {
        contentPopup.classList.remove('show');
        tabSelector.classList.add('show');
        currentTab = null;
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    if (requiredElements.some((el) => !el)) {
        const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
        applyTheme(storedTheme);
        return;
    }

    /* event listeners */
    fabBtn.addEventListener('click', () => {
        if (currentTab !== null || tabSelector.classList.contains('show') || contentPopup.classList.contains('show')) {
            closePopup();
        } else {
            openSelector();
        }
    });

    backdrop.addEventListener('click', closePopup);
    btnClosePopup.addEventListener('click', closePopup);
    btnBack.addEventListener('click', backToSelector);

    tabSelector.querySelectorAll('button[data-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            openTab(btn.dataset.tab);
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    const toastEl = document.getElementById('flashToast');
    if (toastEl) {
        setTimeout(() => {
            toastEl.style.transition = 'opacity 0.4s';
            toastEl.style.opacity = '0';
            setTimeout(() => toastEl.remove(), 400);
        }, 3500);
    }

    renderCounter();
    renderAccruedSalary();
    renderDynamicTotal();
    updateIncrementStatus();
    setInterval(tick, 1000);
    setInterval(syncSnapshot, 60000);

    window.addEventListener('counter-updated', syncSnapshot);

    const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
    applyTheme(storedTheme);

    window.addEventListener('theme-changed', (e) => {
        applyTheme(e.detail.theme);
    });
})();
