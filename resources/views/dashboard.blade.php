<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Counter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    @livewireStyles
    <style>
        /* ── prevent page scroll ── */
        html, body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100vh;
            background: #f8f9fa;
        }

        /* ── floating flash toast ── */
        .flash-toast {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            min-width: 280px;
            max-width: 90vw;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.12);
            border: 0;
        }

        /* ── full‑screen counter ── */
        .counter-fullscreen {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 1rem;
            box-sizing: border-box;
        }

        .counter-value {
            font-size: clamp(3rem, 10vw, 7rem);
            font-weight: 700;
            letter-spacing: 0.02em;
            line-height: 1.1;
        }

        .counter-label {
            font-size: 1rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.10em;
            margin-bottom: 0.5rem;
        }

        /* ── FAB button ── */
        .fab-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #212529;
            color: white;
            border: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1050;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .fab-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
        }
        .fab-btn:active {
            transform: scale(0.96);
        }
        .fab-btn.open {
            background: #dc3545;
            transform: rotate(45deg);
        }

        /* ── backdrop ── */
        .popup-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 1040;
            display: none;
        }
        .popup-backdrop.show {
            display: block;
        }

        /* ── tab selector popup (step 1) ── */
        .tab-selector-popup {
            position: fixed;
            bottom: 96px;
            right: 24px;
            z-index: 1060;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.18);
            display: none;
            flex-direction: column;
            overflow: hidden;
            min-width: 180px;
        }
        .tab-selector-popup.show {
            display: flex;
        }
        .tab-selector-popup button {
            border: none;
            background: none;
            padding: 14px 20px;
            font-size: 0.95rem;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            border-bottom: 1px solid #e9ecef;
            color: #212529;
            transition: background 0.15s;
        }
        .tab-selector-popup button:last-child {
            border-bottom: none;
        }
        .tab-selector-popup button:hover {
            background: #f1f3f5;
        }
        .tab-selector-popup button:active {
            background: #e9ecef;
        }

        /* ── content popup (step 2) ── */
        .content-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1060;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.18);
            display: none;
            width: 60vw;
            max-width: 600px;
            max-height: 70vh;
            overflow: hidden;
            flex-direction: column;
        }
        .content-popup.show {
            display: flex;
        }

        .content-popup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
        }
        .content-popup-header .title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .content-popup-header .btn-back,
        .content-popup-header .btn-close-popup {
            border: none;
            background: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #6c757d;
            padding: 0 4px;
            line-height: 1;
        }
        .content-popup-header .btn-back:hover,
        .content-popup-header .btn-close-popup:hover {
            color: #212529;
        }

        .content-popup-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 16px;
            flex: 1;
        }

        /* ── summary cards ── */
        .data-card {
            border: 0;
            box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.12);
        }

        /* ── dark mode overrides ── */
        [data-bs-theme="dark"] body {
            background: #121212 !important;
        }
        [data-bs-theme="dark"] .counter-value {
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .counter-label,
        [data-bs-theme="dark"] #incrementStatus {
            color: #aaa !important;
        }
        [data-bs-theme="dark"] .fab-btn {
            background: #343a40;
        }
        [data-bs-theme="dark"] .fab-btn.open {
            background: #c82333;
        }
        [data-bs-theme="dark"] .tab-selector-popup,
        [data-bs-theme="dark"] .content-popup {
            background: #1e1e1e;
            box-shadow: 0 8px 30px rgba(0,0,0,0.6);
        }
        [data-bs-theme="dark"] .tab-selector-popup button {
            color: #e0e0e0;
            border-bottom-color: #333;
        }
        [data-bs-theme="dark"] .tab-selector-popup button:hover {
            background: #333;
        }
        [data-bs-theme="dark"] .content-popup-header {
            border-bottom-color: #333;
        }
        [data-bs-theme="dark"] .content-popup-header .btn-back,
        [data-bs-theme="dark"] .content-popup-header .btn-close-popup {
            color: #aaa;
        }
        [data-bs-theme="dark"] .content-popup-header .btn-back:hover,
        [data-bs-theme="dark"] .content-popup-header .btn-close-popup:hover {
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .data-card {
            background: #1e1e1e;
            box-shadow: 0 0.5rem 1.25rem rgba(0,0,0,0.4);
        }
        [data-bs-theme="dark"] .data-card .card-body {
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background: #2a2a2a;
            border-color: #444;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background: #2a2a2a;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .table {
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .table-striped > tbody > tr:nth-of-type(odd) > * {
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .modal-content {
            background: #1e1e1e;
            color: #e0e0e0;
        }

        @media (max-width: 576px) {
            .content-popup {
                width: 85vw;
                max-width: none;
            }
        }
    </style>
</head>
<body>

{{-- Flash toast ──────────────────── --}}
@if (session('status'))
    <div class="alert alert-success flash-toast text-center" id="flashToast">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger flash-toast" id="flashToast">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Main counter (always visible) ──── --}}
<div class="counter-fullscreen">
    {{-- <div class="counter-label">Current Counter</div> --}}
    <div id="counterValue" class="counter-value">RM {{ number_format($snapshot['counter'], 2) }}</div>

    {{-- Incrementing status indicator --}}
    <div id="incrementStatus" class="mt-2" style="font-size: 0.9rem; min-height: 1.2rem;"></div>
</div>

{{-- Backdrop ────────────────────────── --}}
<div class="popup-backdrop" id="popupBackdrop"></div>

{{-- FAB button ──────────────────────── --}}
<button class="fab-btn" id="fabBtn" type="button" aria-label="Open menu">
    ☰
</button>

{{-- Step 1: tab selector popup ──────── --}}
<div class="tab-selector-popup" id="tabSelector">
    <button type="button" data-tab="transactions">Transaction Log</button>
    <button type="button" data-tab="calendar">Workday Calendar</button>
    <button type="button" data-tab="schedules">Salary Schedules</button>
    <button type="button" data-tab="settings">Settings</button>
</div>

{{-- Step 2: content popup ────────────── --}}
<div class="content-popup" id="contentPopup">
    <div class="content-popup-header">
        <button class="btn-back" id="btnBack" type="button" aria-label="Back">←</button>
        <span class="title" id="popupTitle">Transaction Log</span>
        <button class="btn-close-popup" id="btnClosePopup" type="button" aria-label="Close">✕</button>
    </div>
    <div class="content-popup-body" id="contentPopupBody">
        {{-- Transaction Log content ──── --}}
        <div id="panel-transactions">
            <livewire:transaction-log />
        </div>

        {{-- Workday Calendar content ──── --}}
        <div id="panel-calendar" class="d-none">
            <livewire:workday-calendar />
        </div>

        {{-- Salary Schedule content ──── --}}
        <div id="panel-schedules" class="d-none">
            <livewire:salary-schedule-manager />
        </div>

        {{-- Settings content ──── --}}
        <div id="panel-settings" class="d-none">
            <livewire:settings-manager />
        </div>
    </div>
</div>

{{-- Scripts ──────────────────────────── --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    /* ── DOM refs ── */
    const counterElement = document.getElementById('counterValue');


    const accruedSalarySummary = document.getElementById('accruedSalarySummary');
    const datetimeInput = null;
    const typeInput = null;
    const categoryInput = null;
    const fabBtn = document.getElementById('fabBtn');
    const backdrop = document.getElementById('popupBackdrop');
    const tabSelector = document.getElementById('tabSelector');
    const contentPopup = document.getElementById('contentPopup');
    const popupTitle = document.getElementById('popupTitle');
    const btnBack = document.getElementById('btnBack');
    const btnClosePopup = document.getElementById('btnClosePopup');
    const panelTransactions = document.getElementById('panel-transactions');
    const panelCalendar = document.getElementById('panel-calendar');
    const panelSchedules = document.getElementById('panel-schedules');
    const panelSettings = document.getElementById('panel-settings');

    let currentTab = null;

    /* ── state ── */
    let currentValue = Number({{ $snapshot['counter'] }});
    let accruedSalaryValue = Number({{ $snapshot['accrued_salary'] }});
    let incrementPerSecond = Number({{ $snapshot['increment_per_second'] }});

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    /* ── render helpers ── */
    function renderCounter() {
        counterElement.textContent = `RM ${formatter.format(currentValue)}`;
    }
    function renderAccruedSalary() {
        const el = document.getElementById('accruedSalarySummary');
        if (el) {
            el.textContent = `RM ${formatter.format(accruedSalaryValue)}`;
        }
    }

    /* ── category filter ── */
    function filterCategories() {
        // Handled inside Livewire component
    }

    /* ── increment status ── */
    function updateIncrementStatus() {
        const statusEl = document.getElementById('incrementStatus');
        if (incrementPerSecond > 0) {
            statusEl.textContent = 'INCREMENTING (WORK!)';
            statusEl.style.color = '#6c757d';
        } else {
            statusEl.textContent = 'NOT INCREMENTING (RELAX!)';
            statusEl.style.color = '#6c757d';
        }
    }

    /* ── tick ── */
    function tick() {
        currentValue += incrementPerSecond;
        accruedSalaryValue += incrementPerSecond;
        renderCounter();
        renderAccruedSalary();
        updateIncrementStatus();
    }

    /* ── sync from backend ── */
    async function syncSnapshot() {
        const response = await fetch('{{ route('counter.snapshot') }}', {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        currentValue = Number(data.counter);
        accruedSalaryValue = Number(data.accrued_salary);
        incrementPerSecond = Number(data.increment_per_second);
        renderCounter();
        renderAccruedSalary();
        updateIncrementStatus();
    }

    /* ── popup helpers ── */
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

        panelTransactions.classList.add('d-none');
        panelCalendar.classList.add('d-none');
        panelSchedules.classList.add('d-none');
        panelSettings.classList.add('d-none');

        if (tab === 'transactions') {
            panelTransactions.classList.remove('d-none');
            popupTitle.textContent = 'Transaction Log';
            return;
        }

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

    /* ── event listeners ── */
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

    tabSelector.querySelectorAll('button[data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            openTab(btn.dataset.tab);
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePopup();
    });

    /* ── flatpickr is initialized inside the Livewire component via wire:ignore -- no need here */
    /* ── category filter handled inside Livewire component */

    /* ── auto-dismiss flash toast ── */
    const toastEl = document.getElementById('flashToast');
    if (toastEl) {
        setTimeout(() => {
            toastEl.style.transition = 'opacity 0.4s';
            toastEl.style.opacity = '0';
            setTimeout(() => toastEl.remove(), 400);
        }, 3500);
    }

    /* ── start ── */
    renderCounter();
    renderAccruedSalary();
    updateIncrementStatus();
    setInterval(tick, 1000);
    setInterval(syncSnapshot, 60000);

    // Update counter when a transaction is saved/deleted inside the Livewire component
    window.addEventListener('counter-updated', syncSnapshot);

    /* ── theme ── */
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    // Apply stored theme on page load
    const storedTheme = localStorage.getItem('theme') || '{{ $theme }}';
    applyTheme(storedTheme);

    // Listen for theme changes from the Livewire settings component
    window.addEventListener('theme-changed', (e) => {
        applyTheme(e.detail.theme);
    });
</script>
@livewireScripts
</body>
</html>
