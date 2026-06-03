<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Variance Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/projection.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'variance-analysis'])

<div class="container-fluid py-4 px-3 px-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Variance Analysis</h1>
            <p class="text-secondary mb-2">Load a saved projection scenario and compare with actual EOTM values for variance tracking</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card panel-card mb-3">
                <div class="card-header">Scenario</div>
                <div class="card-body">
                    <div class="input-subcard mb-0">
                        <div class="mb-3">
                            <label for="scenarioSelect" class="form-label form-label-sm">Select a Saved Scenario</label>
                            <select id="scenarioSelect" class="form-select compact-input"></select>
                        </div>
                        <div class="d-grid gap-2">
                            <div class="row">
                                <div class="col-12">
                                    <button id="loadScenarioBtn" class="btn btn-dark w-100" type="button">Load Scenario</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">
                    <div>Actuals from History</div>
                    <div class="small text-secondary fw-normal">Selected Month: <span id="targetMonthDisplay">-</span></div>
                </div>
                <div class="card-body">
                    <div id="actualInputsFieldset">
                        <div class="projection-input-tabs nav" id="vaInputTabs" role="tablist">
                            <button class="projection-input-tab active" id="va-tab-balances" data-bs-toggle="tab" data-bs-target="#va-pane-balances" type="button" role="tab" aria-controls="va-pane-balances" aria-selected="true" title="COH / ELR / EPF" data-bs-title="COH / ELR / EPF" data-bs-placement="top">
                                <i class="fa-solid fa-wallet"></i>
                            </button>
                            <button class="projection-input-tab" id="va-tab-expenses" data-bs-toggle="tab" data-bs-target="#va-pane-expenses" type="button" role="tab" aria-controls="va-pane-expenses" aria-selected="false" title="Expense Categories" data-bs-title="Expense Categories" data-bs-placement="top">
                                <i class="fa-solid fa-basket-shopping"></i>
                            </button>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="va-pane-balances" role="tabpanel" aria-labelledby="va-tab-balances" tabindex="0">
                                <div class="input-subcard">
                                    <h2 class="section-subtitle">COH at Month End</h2>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text">RM</span>
                                        <input id="actualClosingCoh" type="text" class="form-control compact-input va-input-control" readonly>
                                    </div>

                                    <h2 class="section-subtitle">ELR at Month End</h2>
                                    <div class="input-group input-group-sm mb-3">
                                        <span class="input-group-text">RM</span>
                                        <input id="actualClosingElr" type="text" class="form-control compact-input va-input-control" readonly>
                                    </div>

                                    <h2 class="section-subtitle">EPF at Month End</h2>
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text">RM</span>
                                        <input id="actualClosingEpf" type="text" class="form-control compact-input va-input-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="va-pane-expenses" role="tabpanel" aria-labelledby="va-tab-expenses" tabindex="0">
                                <div class="input-subcard mb-0">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <h2 class="section-subtitle mb-0">Expense Values by Category</h2>
                                        <div class="small text-secondary text-end" id="historyExpenseSourceLabel">Auto from History</div>
                                    </div>
                                    <hr class="section-divider">
                                    <div class="table-responsive mb-2">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Actual Amount</th>
                                            </tr>
                                            </thead>
                                            <tbody id="actualExpenseCategoryRows"></tbody>
                                            <tfoot>
                                            <tr class="table-light fw-semibold">
                                                <td>Total Expenses</td>
                                                <td class="text-end" id="actualExpensesTotal">RM 0.00</td>
                                            </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card">
                <div class="card-header">Monthly Comparison</div>
                <div class="card-body p-0">
                    <div class="results-wrap va-results-wrap">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <colgroup>
                                    <col span="7" style="width:14.2857%">
                                </colgroup>
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Month</th>
                                    <th>COH</th>
                                    <th>COH Variance</th>
                                    <th>ELR</th>
                                    <th>ELR Variance</th>
                                    <th>EPF</th>
                                    <th>EPF Variance</th>
                                </tr>
                                </thead>
                                <tbody id="planActualRows">
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">Load a scenario to begin tracking actual values.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="statusMessage" class="small" role="status" aria-live="polite"></div>

@php
    $initialScenarios = $scenarios->map(fn ($scenario) => [
        'id' => $scenario->id,
        'name' => $scenario->name,
        'notes' => $scenario->notes,
        'updated_at' => optional($scenario->updated_at)->toDateTimeString(),
    ])->values();
    $expenseCategoriesPayload = $expenseCategories->map(fn ($category) => [
        'id' => (int) $category['id'],
        'name' => $category['name'],
    ])->values();
@endphp

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/edge-nav.js') }}"></script>
<script>
    window.varianceAnalysisConfig = {
        initialScenarios: @json($initialScenarios),
        expenseCategories: @json($expenseCategoriesPayload),
        showScenarioBase: '{{ url('/variance-analysis/scenarios') }}',
        saveActualsBase: '{{ url('/variance-analysis/scenarios') }}',
    };
</script>
<script src="{{ asset('js/variance-analysis-page.js') }}"></script>
</body>
</html>
