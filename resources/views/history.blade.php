<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>History</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/projection.css') }}" rel="stylesheet">
    <link href="{{ asset('css/history.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'history'])

<div class="container-fluid history-page py-4 px-3 px-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">History</h1>
            <p class="text-secondary mb-2">Month-end TFP and category-level income and expense trends</p>
        </div>
    </div>

    <div class="row g-3 history-layout">
        <div class="col-xl-4">
            <div class="card panel-card history-input-card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-8">
                            <div>Monthly Inputs</div>
                            <div class="small text-secondary fw-normal">Selected Month: <span id="selectedMonthDisplay">-</span></div>
                        </div>
                        <div class="col-4 d-flex align-items-center justify-content-end">
                            <button id="saveHistoryBtn" class="w-50 btn btn-dark btn-sm" type="button">Save</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="projection-input-tabs nav" id="historyInputTabs" role="tablist">
                        <button class="projection-input-tab active" id="history-tab-balances" data-bs-toggle="tab" data-bs-target="#history-pane-balances" type="button" role="tab" aria-controls="history-pane-balances" aria-selected="true" aria-label="Month and Balances" data-bs-title="Month and Balances">
                            <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                        </button>
                        <button class="projection-input-tab" id="history-tab-income" data-bs-toggle="tab" data-bs-target="#history-pane-income" type="button" role="tab" aria-controls="history-pane-income" aria-selected="false" aria-label="Income" data-bs-title="Income">
                            <i class="fa-solid fa-money-bill-trend-up" aria-hidden="true"></i>
                        </button>
                        <button class="projection-input-tab" id="history-tab-expenses" data-bs-toggle="tab" data-bs-target="#history-pane-expenses" type="button" role="tab" aria-controls="history-pane-expenses" aria-selected="false" aria-label="Expenses" data-bs-title="Expenses">
                            <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="history-pane-balances" role="tabpanel" aria-labelledby="history-tab-balances" tabindex="0">
                            <div class="input-subcard">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label for="historyMonth" class="form-label form-label-sm">Month</label>
                                        <input id="historyMonth" class="form-control compact-input month-input" type="text" value="{{ $latestMonth }}" autocomplete="off">
                                    </div>
                                    <div class="col-12">
                                        <label for="monthEndTotalInput" class="form-label form-label-sm">TFP at Month End</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="monthEndTotalInput" class="form-control compact-input" type="number" step="0.01" min="0" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="closingCohInput" class="form-label form-label-sm">COH at Month End</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="closingCohInput" class="form-control compact-input" type="number" step="0.01" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="closingElrInput" class="form-label form-label-sm">ELR at Month End</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="closingElrInput" class="form-control compact-input" type="number" step="0.01" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="closingEpfInput" class="form-label form-label-sm">EPF at Month End</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="closingEpfInput" class="form-control compact-input" type="number" step="0.01" min="0" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="history-pane-expenses" role="tabpanel" aria-labelledby="history-tab-expenses" tabindex="0">
                            <div class="input-subcard history-category-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="section-subtitle mb-0">Expenses</h2>
                                </div>
                                <div id="expenseInputs" class="history-category-list"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="history-pane-income" role="tabpanel" aria-labelledby="history-tab-income" tabindex="0">
                            <div class="input-subcard history-category-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h2 class="section-subtitle mb-0">Income</h2>
                                </div>
                                <div id="incomeInputs" class="history-category-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card history-visualisation-card">
                <div class="card-header">
                    <div class="history-visualisation-header">
                        <div class="history-visualisation-title">
                            <div>Visualisation</div>
                            <div class="small text-secondary fw-normal">Latest: <span id="latestMonthDisplay">-</span></div>
                        </div>
                        <div class="history-visualisation-actions">
                            <div id="currentAccrualControls" class="form-check history-current-accrual-toggle d-none">
                                <input id="showCurrentAccrualOverlay" class="form-check-input" type="checkbox">
                                <label class="form-check-label" for="showCurrentAccrualOverlay">Unpaid accrual</label>
                            </div>
                            <div id="expenseWaffleValueControls" class="btn-group btn-group-sm history-waffle-value-toggle d-none" role="group" aria-label="Expense category value display">
                                <input class="btn-check" type="radio" name="expenseWaffleValueMode" id="expenseWaffleModeSen" value="sen" checked>
                                <label class="btn btn-outline-secondary" for="expenseWaffleModeSen">sen/RM</label>
                                <input class="btn-check" type="radio" name="expenseWaffleValueMode" id="expenseWaffleModeRm" value="rm">
                                <label class="btn btn-outline-secondary" for="expenseWaffleModeRm">RM</label>
                            </div>
                            <div class="history-visualisation-switcher">
                                <select id="historyVisualisationSelect" class="form-select form-select-sm history-visualisation-select" aria-label="History visualisation">
                                    <option value="coh" selected>TFP Trend</option>
                                    <option value="coh-breakdown">COH, ELR and EPF</option>
                                    <option value="income-expense">Income and Expenses</option>
                                    <option value="expense-category">Expenses by Category</option>
                                </select>
                                <div class="history-window-controls" aria-label="History month window controls">
                                    <button id="previousWindowBtn" class="btn btn-outline-secondary btn-sm" type="button" aria-label="Show previous 12 months">
                                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                    </button>
                                    <button id="nextWindowBtn" class="btn btn-outline-secondary btn-sm" type="button" aria-label="Show next 12 months">
                                        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="historyCohPane" class="history-visualisation-pane">
                        <div class="history-chart-wrap">
                            <canvas id="historyCohChart"></canvas>
                        </div>
                    </div>
                    <div id="historyCohBreakdownPane" class="history-visualisation-pane d-none">
                        <div class="history-chart-wrap history-chart-wrap-compact">
                            <canvas id="historyCohBreakdownChart"></canvas>
                        </div>
                    </div>
                    <div id="historyIncomeExpensePane" class="history-visualisation-pane d-none">
                        <div class="history-chart-wrap history-chart-wrap-compact">
                            <canvas id="historyIncomeExpenseChart"></canvas>
                        </div>
                    </div>
                    <div id="historyExpenseCategoryPane" class="history-visualisation-pane d-none">
                        <div class="history-waffle-chart-wrap">
                            <div id="historyExpenseCategoryChart" class="history-waffle-chart" role="group" aria-label="Expenses by category"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="statusMessage" aria-live="polite"></div>

@php
    $expenseCategoriesPayload = $expenseCategories->map(fn ($category) => [
        'id' => (int) $category['id'],
        'name' => $category['name'],
    ])->values();
    $incomeCategoriesPayload = $incomeCategories->map(fn ($category) => [
        'id' => (int) $category['id'],
        'name' => $category['name'],
    ])->values();
@endphp

<script>
    window.historyConfig = {
        latestMonth: @json($latestMonth),
        monthsEndpoint: @json(route('history.months')),
        saveEndpoint: @json(route('history.months.save')),
        counterSnapshotEndpoint: @json(route('counter.snapshot')),
        expenseCategories: @json($expenseCategoriesPayload),
        incomeCategories: @json($incomeCategoriesPayload),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/history-page.js') }}"></script>
@livewireScripts
</body>
</html>
