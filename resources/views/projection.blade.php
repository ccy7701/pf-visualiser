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
                        <div class="row">
                            <div class="col-6">
                                <button id="runProjectionBtn" class="btn btn-dark w-100">Run</button>
                            </div>
                            <div class="col-6">
                                <button id="saveScenarioBtn" class="btn btn-outline-dark w-100">Save</button>
                            </div>
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
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="compareScenarioA" class="form-label form-label-sm">Compare Scenario A</label>
                            <select id="compareScenarioA" class="form-select compact-input"></select>
                        </div>
                        <div class="col-6">
                            <label for="compareScenarioB" class="form-label form-label-sm">Compare Scenario B</label>
                            <select id="compareScenarioB" class="form-select compact-input"></select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center align-items-center">
                        <button id="compareScenariosBtn" class="btn btn-outline-dark w-50" type="button">Compare</button>
                    </div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">Projection Inputs</div>
                <div class="card-body">
                    <div class="projection-input-tabs nav" id="projectionInputTabs" role="tablist">
                        <button class="projection-input-tab active" id="tab-scenario" data-bs-toggle="tab" data-bs-target="#pane-scenario" type="button" role="tab" aria-controls="pane-scenario" aria-selected="true" data-bs-title="Starting Parameters" data-bs-placement="top"><i class="fa-solid fa-calendar-days"></i></button>
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
                                <h2 class="section-subtitle">Starting Parameters</h2>
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

                                <h3 class="section-subtitle">Monthly Budget Selection</h3>
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
    window.projectionConfig = {
        runEndpoint: '{{ route('projection.run') }}',
        saveEndpoint: '{{ route('projection.scenarios.save') }}',
        compareEndpoint: '{{ route('projection.compare') }}',
        showScenarioBase: '{{ url('/projection/scenarios') }}',
        initialScenarios: @json($initialScenarios),
    };
</script>
<script src="{{ asset('js/projection-page.js') }}"></script>
</body>
</html>
