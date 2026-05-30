<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COH Projection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css" rel="stylesheet">
    <style>
        :root {
            --page-bg: #f8f9fa;
            --card-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.08);
            --accent: #212529;
        }

        body {
            background: var(--page-bg);
            min-height: 100vh;
            color: #212529;
        }

        .module-nav-dock {
            position: fixed;
            top: 50vh;
            left: 14px;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1100;
        }

        .module-nav-label {
            background: rgba(255, 255, 255, 0.92);
            color: #212529;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            padding: 0.25rem 0.65rem;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-6px);
            pointer-events: none;
            transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s ease;
        }

        .module-nav-dock:hover .module-nav-label,
        .module-nav-dock:focus-within .module-nav-label {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }

        .module-nav-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #212529;
            color: #fff;
            border: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .module-nav-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
            color: #fff;
        }

        .panel-card {
            border: 0;
            box-shadow: var(--card-shadow);
        }

        .panel-card .card-header {
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }

        .compact-input {
            font-size: 0.9rem;
        }
        .section-subtitle {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .section-divider {
            margin: 0 0 0.75rem;
            border: 0;
            border-top: 1px solid #dee2e6;
        }
        .input-subcard {
            border: 1px solid #dee2e6;
            border-radius: 0.65rem;
            padding: 0.75rem;
            background: #fff;
            margin-bottom: 0.75rem;
        }
        .projection-input-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        .projection-input-tab {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #212529;
            background: #fff;
            color: #212529;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .projection-input-tab:hover,
        .projection-input-tab:focus-visible {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
            color: #fff;
            background: #212529;
        }
        .projection-input-tab.active {
            background: #212529;
            color: #fff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .results-wrap {
            max-height: 55vh;
            overflow: auto;
        }

        .table-sm th,
        .table-sm td {
            white-space: nowrap;
            vertical-align: middle;
        }

        #projectionRows td:first-child,
        #projectionRows td:last-child,
        .results-wrap thead th:first-child,
        .results-wrap thead th:last-child {
            padding-left: 0.85rem;
            padding-right: 0.85rem;
        }

        .negative-value {
            color: #dc3545;
        }
        .projection-table {
            table-layout: fixed;
            width: 100%;
        }
        .projection-table th,
        .projection-table td {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chart-wrap {
            position: relative;
            height: 420px;
            width: 100%;
        }

        #statusMessage {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 1100;
            max-width: 360px;
            width: calc(100% - 2rem);
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            color: #fff;
            background: rgba(33, 37, 41, 0.95);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        #statusMessage.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        #statusMessage.is-error {
            background: rgba(220, 53, 69, 0.95);
        }

        @media (max-width: 991px) {
            .module-nav-dock {
                left: 8px;
                gap: 0.35rem;
            }

            .module-nav-label {
                font-size: 0.72rem;
                padding: 0.2rem 0.5rem;
            }

            .module-nav-btn {
                width: 50px;
                height: 50px;
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
<div class="module-nav-dock" aria-label="Counter navigation">
    <a href="{{ route('dashboard') }}" class="module-nav-btn" aria-label="Back to counter">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
    </a>
    <span class="module-nav-label">Counter</span>
</div>

<div class="container-fluid py-4 px-3 px-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Cumulative COH Projection</h1>
            <p class="text-secondary mb-2">Projection engine to visualise budget plans over time</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card panel-card mb-3">
                <div class="card-header">Scenario Controls</div>
                <div class="card-body py-3">
                    <div class="input-subcard">
                        <div class="mb-2">
                            <label for="saveName" class="form-label form-label-sm">Scenario Name</label>
                            <input id="saveName" type="text" class="form-control compact-input" placeholder="Example: Base Case 2026">
                        </div>
                        <div class="mb-3">
                            <label for="saveNotes" class="form-label form-label-sm">Notes</label>
                            <textarea id="saveNotes" class="form-control compact-input" rows="2" placeholder="Optional notes"></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button id="runProjectionBtn" class="btn btn-dark">Run Projection</button>
                            <button id="saveScenarioBtn" class="btn btn-outline-dark">Save Scenario</button>
                        </div>
                    </div>

                    <div class="input-subcard mb-0">
                        <div class="mb-0">
                            <label for="savedScenarioId" class="form-label form-label-sm">Load Scenario</label>
                            <div class="input-group">
                                <select id="savedScenarioId" class="form-select compact-input"></select>
                                <button id="loadScenarioBtn" class="btn btn-outline-secondary" type="button">Load</button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card panel-card mb-3">
                <div class="card-header">Comparisons</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label for="compareScenarioA" class="form-label form-label-sm">Compare Scenario A</label>
                        <select id="compareScenarioA" class="form-select compact-input"></select>
                    </div>
                    <div class="mb-2">
                        <label for="compareScenarioB" class="form-label form-label-sm">Compare Scenario B</label>
                        <select id="compareScenarioB" class="form-select compact-input"></select>
                    </div>
                    <button id="compareScenariosBtn" class="btn btn-outline-dark w-100" type="button">Compare</button>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">Projection Inputs</div>
                <div class="card-body">
                    <div class="projection-input-tabs nav" id="projectionInputTabs" role="tablist">
                        <button class="projection-input-tab active" id="tab-scenario" data-bs-toggle="tab" data-bs-target="#pane-scenario" type="button" role="tab" aria-controls="pane-scenario" aria-selected="true" data-bs-title="Scenario" data-bs-placement="top"><i class="fa-solid fa-calendar-days"></i></button>
                        <button class="projection-input-tab" id="tab-employment" data-bs-toggle="tab" data-bs-target="#pane-employment" type="button" role="tab" aria-controls="pane-employment" aria-selected="false" data-bs-title="Employment" data-bs-placement="top"><i class="fa-solid fa-briefcase"></i></button>
                        <button class="projection-input-tab" id="tab-col" data-bs-toggle="tab" data-bs-target="#pane-col" type="button" role="tab" aria-controls="pane-col" aria-selected="false" data-bs-title="Cost of Living" data-bs-placement="top"><i class="fa-solid fa-basket-shopping"></i></button>
                        <button class="projection-input-tab" id="tab-ptptn" data-bs-toggle="tab" data-bs-target="#pane-ptptn" type="button" role="tab" aria-controls="pane-ptptn" aria-selected="false" data-bs-title="PTPTN" data-bs-placement="top"><i class="fa-solid fa-graduation-cap"></i></button>
                        <button class="projection-input-tab" id="tab-bnpl" data-bs-toggle="tab" data-bs-target="#pane-bnpl" type="button" role="tab" aria-controls="pane-bnpl" aria-selected="false" data-bs-title="BNPL" data-bs-placement="top"><i class="fa-solid fa-credit-card"></i></button>
                        <button class="projection-input-tab" id="tab-events" data-bs-toggle="tab" data-bs-target="#pane-events" type="button" role="tab" aria-controls="pane-events" aria-selected="false" data-bs-title="Events" data-bs-placement="top"><i class="fa-solid fa-calendar-plus"></i></button>
                        <button class="projection-input-tab" id="tab-elr" data-bs-toggle="tab" data-bs-target="#pane-elr" type="button" role="tab" aria-controls="pane-elr" aria-selected="false" data-bs-title="ELR Schedules" data-bs-placement="top"><i class="fa-solid fa-piggy-bank"></i></button>
                        <button class="projection-input-tab" id="tab-epf" data-bs-toggle="tab" data-bs-target="#pane-epf" type="button" role="tab" aria-controls="pane-epf" aria-selected="false" data-bs-title="EPF" data-bs-placement="top"><i class="fa-solid fa-percent"></i></button>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-scenario" role="tabpanel" aria-labelledby="tab-scenario" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">Scenario</h2>
                                <hr class="section-divider">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Start Month</label>
                                        <input id="startMonth" type="text" class="form-control compact-input month-input" value="{{ now('Asia/Kuala_Lumpur')->startOfMonth()->format('Y-m') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">End Month</label>
                                        <input id="endMonth" type="text" class="form-control compact-input month-input" value="{{ now('Asia/Kuala_Lumpur')->addMonths(11)->startOfMonth()->format('Y-m') }}">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label form-label-sm">Starting COH</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="startingCoh" type="text" inputmode="decimal" class="form-control compact-input money-input" value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label form-label-sm">Starting ELR</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="startingElr" type="text" inputmode="decimal" class="form-control compact-input money-input" value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label form-label-sm">Starting EPF</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="startingEpf" type="text" inputmode="decimal" class="form-control compact-input money-input" value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-employment" role="tabpanel" aria-labelledby="tab-employment" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">Employment</h2>
                                <hr class="section-divider">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Probation Salary</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="probationSalary" type="text" inputmode="decimal" class="form-control compact-input money-input" value="1800.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Confirmed Salary</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="confirmedSalary" type="text" inputmode="decimal" class="form-control compact-input money-input" value="2200.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Probation Months</label>
                                        <input id="probationDuration" type="number" min="0" class="form-control compact-input" value="3">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Salary Start Month</label>
                                        <input id="salaryStartMonth" type="text" class="form-control compact-input month-input" value="{{ now('Asia/Kuala_Lumpur')->startOfMonth()->format('Y-m') }}">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check mt-1">
                                            <input id="salaryPaidInArrears" class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label" for="salaryPaidInArrears">Salary paid in arrears (full-month lag)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-col" role="tabpanel" aria-labelledby="tab-col" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">Cost of Living</h2>
                                <hr class="section-divider">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Expense Category</th>
                                            <th>BCOL</th>
                                            <th>FCOL Lite</th>
                                            <th>FCOL Max</th>
                                        </tr>
                                        </thead>
                                        <tbody id="costAllocationRows"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex justify-content-end mb-3">
                                    <small class="text-secondary" id="budgetTotalsSummary"></small>
                                </div>

                                <h3 class="section-subtitle d-flex justify-content-between align-items-center">
                                    Monthly Budget Selection
                                    <button id="addMonthlyBudgetBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h3>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Budget</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody id="monthlyBudgetRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-ptptn" role="tabpanel" aria-labelledby="tab-ptptn" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">PTPTN</h2>
                                <hr class="section-divider">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Monthly Repayment</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="ptptnMonthlyRepayment" type="number" step="0.01" class="form-control compact-input" value="120.00">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Repayment Start Month</label>
                                        <input id="ptptnRepaymentStartMonth" type="text" class="form-control compact-input month-input">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check mt-1">
                                            <input id="ptptnWaiverGranted" class="form-check-input" type="checkbox">
                                            <label class="form-check-label" for="ptptnWaiverGranted">PTPTN waiver granted (permanent)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-bnpl" role="tabpanel" aria-labelledby="tab-bnpl" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle d-flex justify-content-between align-items-center">BNPL
                                    <button id="addBnplBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h2>
                                <hr class="section-divider">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th>Note</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody id="bnplRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-events" role="tabpanel" aria-labelledby="tab-events" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle d-flex justify-content-between align-items-center">Events
                                    <button id="addEventBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h2>
                                <hr class="section-divider">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Note</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody id="eventRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-elr" role="tabpanel" aria-labelledby="tab-elr" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle d-flex justify-content-between align-items-center">ELR Schedules
                                    <button id="addElrScheduleBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h2>
                                <hr class="section-divider">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody id="elrScheduleRows"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-epf" role="tabpanel" aria-labelledby="tab-epf" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">EPF</h2>
                                <hr class="section-divider">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Employee EPF (%)</label>
                                        <input id="employeeEpfRatePercent" type="number" step="0.01" class="form-control compact-input" value="11.00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Employer EPF (%)</label>
                                        <input id="employerEpfRatePercent" type="number" step="0.01" class="form-control compact-input" value="13.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card mb-3">
                <div class="card-header">Projection Summary</div>
                <div class="card-body">
                    <div class="row g-2" id="summaryCards">
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-secondary">Final COH</div>
                                <div id="summaryFinalCoh" class="fw-semibold">RM 0.00</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-secondary">Final ELR</div>
                                <div id="summaryFinalElr" class="fw-semibold">RM 0.00</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-secondary">Final EPF</div>
                                <div id="summaryFinalEpf" class="fw-semibold">RM 0.00</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2 h-100">
                                <div class="small text-secondary">Lowest COH</div>
                                <div id="summaryLowestCoh" class="fw-semibold">RM 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card mb-3">
                <div class="card-header">Projection Chart</div>
                <div class="card-body">
                    <div class="mb-2" style="max-width: 260px;">
                        <label for="chartType" class="form-label form-label-sm">Chart Type</label>
                        <select id="chartType" class="form-select form-select-sm">
                            <option value="line">Multi-line</option>
                            <option value="stacked_bar">Stacked Bar</option>
                        </select>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="projectionStackedChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card panel-card mb-3">
                <div class="card-header">Projections by Month</div>
                <div class="card-body p-0">
                    <div class="results-wrap">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <colgroup>
                                    <col span="8" style="width:12.5%">
                                </colgroup>
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th class="text-start">Month</th>
                                    <th class="text-end">Opening COH</th>
                                    <th class="text-end">Net Income</th>
                                    <th class="text-end">Expenses</th>
                                    <th class="text-end">Debt</th>
                                    <th class="text-end">Closing COH</th>
                                    <th class="text-end">ELR</th>
                                    <th class="text-end">EPF</th>
                                </tr>
                                </thead>
                                <tbody id="projectionRows">
                                <tr>
                                    <td colspan="8" class="text-center text-secondary py-4">Run a projection to view results.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card" id="comparisonPanel" style="display:none;">
                <div class="card-header">Scenario Comparison</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Scenario</th>
                                <th class="text-end">Final COH</th>
                                <th class="text-end">Final ELR</th>
                                <th class="text-end">Final EPF</th>
                                <th class="text-end">Lowest COH</th>
                                <th class="text-end">Highest COH</th>
                            </tr>
                            </thead>
                            <tbody id="comparisonRows"></tbody>
                        </table>
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
@endphp

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const runEndpoint = '{{ route('projection.run') }}';
    const saveEndpoint = '{{ route('projection.scenarios.save') }}';
    const compareEndpoint = '{{ route('projection.compare') }}';
    const showScenarioBase = '{{ url('/projection/scenarios') }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const initialScenarios = @json($initialScenarios);
    const expenseCategories = @json($expenseCategories);
    const budgetKeys = ['bcol', 'fcol_lite', 'fcol_max'];

    const money = new Intl.NumberFormat('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const monthLabelFormatter = new Intl.DateTimeFormat('en-MY', { month: 'short', year: 'numeric' });
    let projectionChart = null;
    let currentProjectionMonths = [];
    let statusTimer = null;

    function budgetLabel(budget) {
        if (budget === 'fcol_lite') return 'FCOL Lite';
        if (budget === 'fcol_max') return 'FCOL Max';
        return 'BCOL';
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

    function normalizeDecimalInputs(root = document) {
        root.querySelectorAll('input.money-input, input[type="number"][step="0.01"]').forEach((input) => {
            if (input.closest('#projectionRows')) return;
            input.value = formatToTwoDp(input.value);

            input.addEventListener('blur', () => {
                input.value = formatToTwoDp(input.value);
            });
        });
    }

    function createCostAllocationRows(cost = {}) {
        const tbody = document.getElementById('costAllocationRows');
        tbody.innerHTML = '';

        const budgets = cost.budgets || {};
        const allocationsByBudget = {};

        budgetKeys.forEach((key) => {
            allocationsByBudget[key] = {};
            const allocations = budgets[key]?.category_allocations || [];
            allocations.forEach((allocation) => {
                const categoryId = Number(allocation.category_id || 0);
                allocationsByBudget[key][categoryId] = toNumber(allocation.amount, 0);
            });
        });

        expenseCategories.forEach((category) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${category.name}</td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">RM</span>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-col-budget="bcol" data-col-category-id="${category.id}" value="${allocationsByBudget.bcol[category.id] ?? 0}">
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">RM</span>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-col-budget="fcol_lite" data-col-category-id="${category.id}" value="${allocationsByBudget.fcol_lite[category.id] ?? 0}">
                    </div>
                </td>
                <td>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">RM</span>
                        <input type="text" inputmode="decimal" class="form-control form-control-sm money-input" data-col-budget="fcol_max" data-col-category-id="${category.id}" value="${allocationsByBudget.fcol_max[category.id] ?? 0}">
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

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
                    <option value="bcol">BCOL</option>
                    <option value="fcol_lite">FCOL Lite</option>
                    <option value="fcol_max">FCOL Max</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-outline-danger">×</button></td>
        `;
        row.querySelector('[data-col-budget]').value = data.budget || 'bcol';
        row.querySelector('button').addEventListener('click', () => row.remove());
        document.getElementById('monthlyBudgetRows').appendChild(row);
        initMonthPickers();
    }

    function collectCostOfLivingPayload() {
        const budgets = {};

        budgetKeys.forEach((budgetKey) => {
            const allocations = expenseCategories.map((category) => {
                const input = document.querySelector(`[data-col-budget="${budgetKey}"][data-col-category-id="${category.id}"]`);

                return {
                    category_id: category.id,
                    name: category.name,
                    amount: toNumber(input?.value ?? 0, 0),
                };
            });

            budgets[budgetKey] = {
                category_allocations: allocations,
            };
        });

        const monthlyBudgetSelection = Array.from(document.querySelectorAll('#monthlyBudgetRows tr')).map((row) => ({
            month: toMonthOrNull(row.querySelector('[data-col-month]').value),
            budget: row.querySelector('[data-col-budget]').value,
        })).filter((item) => item.month && budgetKeys.includes(item.budget));

        return {
            budgets,
            monthly_budget_selection: monthlyBudgetSelection,
        };
    }

    function attachBudgetAllocationListeners() {
        document.querySelectorAll('[data-col-budget][data-col-category-id]').forEach((input) => {
            input.addEventListener('blur', updateBudgetTotalsSummary);
        });
    }

    function updateBudgetTotalsSummary() {
        const payload = collectCostOfLivingPayload();
        const bcolTotal = (payload.budgets.bcol?.category_allocations || []).reduce((carry, item) => carry + toNumber(item.amount, 0), 0);
        const liteTotal = (payload.budgets.fcol_lite?.category_allocations || []).reduce((carry, item) => carry + toNumber(item.amount, 0), 0);
        const maxTotal = (payload.budgets.fcol_max?.category_allocations || []).reduce((carry, item) => carry + toNumber(item.amount, 0), 0);
        document.getElementById('budgetTotalsSummary').textContent = `BCOL: RM ${money.format(bcolTotal)} | FCOL Lite: RM ${money.format(liteTotal)} | FCOL Max: RM ${money.format(maxTotal)}`;
    }

    function initProjectionInputTabUI() {
        document.querySelectorAll('#projectionInputTabs [data-bs-title]').forEach((el) => {
            new bootstrap.Tooltip(el);
        });

        document.querySelectorAll('#projectionInputTabs [data-bs-toggle="tab"]').forEach((tabButton) => {
            tabButton.addEventListener('shown.bs.tab', () => {
                document.querySelectorAll('#projectionInputTabs .projection-input-tab').forEach((btn) => {
                    btn.classList.toggle('active', btn === tabButton);
                });
            });
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
                probation_salary: toNumber(document.getElementById('probationSalary').value, 0),
                confirmed_salary: toNumber(document.getElementById('confirmedSalary').value, 0),
                probation_duration_months: Math.max(0, Math.trunc(toNumber(document.getElementById('probationDuration').value, 0))),
                salary_start_month: toMonthOrNull(document.getElementById('salaryStartMonth').value),
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

        document.getElementById('probationSalary').value = employment.probation_salary ?? 0;
        document.getElementById('confirmedSalary').value = employment.confirmed_salary ?? 0;
        document.getElementById('probationDuration').value = employment.probation_duration_months ?? 0;
        document.getElementById('salaryStartMonth').value = employment.salary_start_month || '';
        document.getElementById('salaryPaidInArrears').checked = Boolean(employment.salary_paid_in_arrears);

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
        document.getElementById('monthlyBudgetRows').innerHTML = '';
        (cost.monthly_budget_selection || []).forEach(createMonthlyBudgetRow);

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
        initMonthPickers();
    }

    function renderProjection(result) {
        const months = result.months || [];
        currentProjectionMonths = months;
        const summary = result.summary || {};

        const finalCoh = toNumber(summary.final_coh, 0);
        const finalElr = toNumber(summary.final_elr, 0);
        const finalEpf = toNumber(summary.final_epf, 0);
        const lowestCoh = toNumber(summary.lowest_coh, 0);
        document.getElementById('summaryFinalCoh').textContent = `RM ${money.format(finalCoh)}`;
        document.getElementById('summaryFinalElr').textContent = `RM ${money.format(finalElr)}`;
        document.getElementById('summaryFinalEpf').textContent = `RM ${money.format(finalEpf)}`;
        document.getElementById('summaryLowestCoh').textContent = `RM ${money.format(lowestCoh)}`;
        document.getElementById('summaryFinalCoh').classList.toggle('negative-value', finalCoh < 0);
        document.getElementById('summaryFinalElr').classList.toggle('negative-value', finalElr < 0);
        document.getElementById('summaryFinalEpf').classList.toggle('negative-value', finalEpf < 0);
        document.getElementById('summaryLowestCoh').classList.toggle('negative-value', lowestCoh < 0);

        const tbody = document.getElementById('projectionRows');
        tbody.innerHTML = '';

        if (!months.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-secondary py-4">No projection rows returned.</td></tr>';
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
            tr.innerHTML = `
                <td>${formatMonthLabel(row.month)}</td>
                <td class="text-end ${openingCoh < 0 ? 'negative-value' : ''}">${money.format(openingCoh)}</td>
                <td class="text-end ${netIncome < 0 ? 'negative-value' : ''}">${money.format(netIncome)}</td>
                <td class="text-end ${expenses < 0 ? 'negative-value' : ''}">${money.format(expenses)}</td>
                <td class="text-end ${debtServicing < 0 ? 'negative-value' : ''}">${money.format(debtServicing)}</td>
                <td class="text-end ${closingCoh < 0 ? 'negative-value' : ''}">${money.format(closingCoh)}</td>
                <td class="text-end ${closingElr < 0 ? 'negative-value' : ''}">${money.format(closingElr)}</td>
                <td class="text-end ${closingEpf < 0 ? 'negative-value' : ''}">${money.format(closingEpf)}</td>
            `;
            tbody.appendChild(tr);
        }

        renderProjectionChart(months);
    }

    function renderProjectionChart(months) {
        const ctx = document.getElementById('projectionStackedChart');
        if (!ctx) return;
        const selectedType = document.getElementById('chartType')?.value || 'line';
        const isStackedBar = selectedType === 'stacked_bar';

        const labels = months.map((row) => formatMonthLabel(row.month));
        const coh = months.map((row) => toNumber(row.closing_coh, 0));
        const elr = months.map((row) => toNumber(row.closing_elr, 0));
        const epf = months.map((row) => toNumber(row.closing_epf, 0));

        if (projectionChart) {
            projectionChart.destroy();
        }

        projectionChart = new Chart(ctx, {
            type: isStackedBar ? 'bar' : 'line',
            data: {
                labels,
                datasets: [
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
                ],
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
        const panel = document.getElementById('comparisonPanel');
        const tbody = document.getElementById('comparisonRows');
        tbody.innerHTML = '';

        if (!comparisons || comparisons.length === 0) {
            panel.style.display = 'none';
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

        panel.style.display = '';
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
            document.getElementById('savedScenarioId'),
            document.getElementById('compareScenarioA'),
            document.getElementById('compareScenarioB'),
        ];

        selects.forEach((select) => {
            const current = select.value;
            select.innerHTML = '<option value="">Select scenario...</option>';
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
        initialScenarios.unshift(scenario);
        populateScenarioSelects(initialScenarios);
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
                },
            });
        });
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

    document.getElementById('addMonthlyBudgetBtn').addEventListener('click', () => createMonthlyBudgetRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        budget: 'bcol',
    }));

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

        setStatus('Saving scenario...');

        try {
            const payload = collectPayload();
            const response = await postJson(saveEndpoint, {
                name,
                notes: document.getElementById('saveNotes').value.trim(),
                ...payload,
            });

            renderProjection(response.result);
            addScenarioOption(response.scenario);
            document.getElementById('savedScenarioId').value = String(response.scenario.id);
            setStatus(response.message || 'Scenario saved.');
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    document.getElementById('loadScenarioBtn').addEventListener('click', async () => {
        const scenarioId = document.getElementById('savedScenarioId').value;

        if (!scenarioId) {
            setStatus('Choose a scenario to load.', true);
            return;
        }

        setStatus('Loading scenario...');

        try {
            const response = await fetch(`${showScenarioBase}/${scenarioId}`, {
                headers: { 'Accept': 'application/json' },
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data?.message || 'Unable to load scenario.');
            }

            applyPayload(data.scenario.parameters_json || {});
            renderProjection(data.result || {});
            document.getElementById('saveName').value = data.scenario.name || '';
            document.getElementById('saveNotes').value = data.scenario.notes || '';
            setStatus('Scenario loaded.');
        } catch (error) {
            setStatus(error.message, true);
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

    populateScenarioSelects(initialScenarios);
    initProjectionInputTabUI();
    createCostAllocationRows();
    initMonthPickers();
    normalizeDecimalInputs();
    createMonthlyBudgetRow({
        month: toMonthOrNull(document.getElementById('startMonth').value),
        budget: 'bcol',
    });

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
</script>
</body>
</html>
