<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css" rel="stylesheet">
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
            <p class="text-secondary mb-2">Month-end COH and category-level income/expense trends</p>
        </div>
    </div>

    <div class="row g-3 history-layout">
        <div class="col-xl-4">
            <div class="card panel-card history-input-card">
                <div class="card-header">
                    <div>Monthly Inputs</div>
                    <div class="small text-secondary fw-normal">Saved per month</div>
                </div>
                <div class="card-body">
                    <div class="input-subcard">
                        <div class="row g-2 align-items-end">
                            <div class="col-7">
                                <label for="historyMonth" class="form-label form-label-sm">Month</label>
                                <input id="historyMonth" class="form-control compact-input month-input" type="text" value="{{ $latestMonth }}" autocomplete="off">
                            </div>
                            <div class="col-5">
                                <button id="loadMonthBtn" class="btn btn-outline-secondary w-100" type="button">Load</button>
                            </div>
                        </div>
                    </div>

                    <div class="input-subcard">
                        <label for="closingCohInput" class="form-label form-label-sm">COH at Month End</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">RM</span>
                            <input id="closingCohInput" class="form-control compact-input" type="number" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="projection-input-tabs nav" id="historyInputTabs" role="tablist">
                        <button class="projection-input-tab active" id="history-tab-expenses" data-bs-toggle="tab" data-bs-target="#history-pane-expenses" type="button" role="tab" aria-controls="history-pane-expenses" aria-selected="true" data-bs-title="Expenses" data-bs-placement="top">
                            <i class="fa-solid fa-basket-shopping" aria-hidden="true"></i>
                        </button>
                        <button class="projection-input-tab" id="history-tab-income" data-bs-toggle="tab" data-bs-target="#history-pane-income" type="button" role="tab" aria-controls="history-pane-income" aria-selected="false" data-bs-title="Income" data-bs-placement="top">
                            <i class="fa-solid fa-money-bill-trend-up" aria-hidden="true"></i>
                        </button>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="history-pane-expenses" role="tabpanel" aria-labelledby="history-tab-expenses" tabindex="0">
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

                    <div class="d-grid">
                        <button id="saveHistoryBtn" class="btn btn-dark" type="button">Save Month</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card mb-3">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
                        <div>
                            <div>COH Trend</div>
                            <div class="small text-secondary fw-normal">Latest: <span id="latestMonthDisplay">-</span></div>
                        </div>
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
                <div class="card-body">
                    <div class="history-chart-wrap">
                        <canvas id="historyCohChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">Income and Expenses</div>
                <div class="card-body">
                    <div class="history-chart-wrap history-chart-wrap-compact">
                        <canvas id="historyIncomeExpenseChart"></canvas>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/edge-nav.js') }}"></script>
<script>
    window.historyConfig = {
        latestMonth: @json($latestMonth),
        monthsEndpoint: @json(route('history.months')),
        saveEndpoint: @json(route('history.months.save')),
        expenseCategories: @json($expenseCategoriesPayload),
        incomeCategories: @json($incomeCategoriesPayload),
    };
</script>
<script src="{{ asset('js/history-page.js') }}"></script>
</body>
</html>
