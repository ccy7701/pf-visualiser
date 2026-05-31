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
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/projection.css') }}" rel="stylesheet">
</head>
<body>
<div class="module-nav-dock" aria-label="Counter navigation">
    <a href="{{ route('counter') }}" class="module-nav-btn" aria-label="Back to counter">
        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
    </a>
    <span class="module-nav-label">Counter</span>
</div>
<div class="module-nav-dock module-nav-dock-right" aria-label="Plan versus actual navigation">
    <span class="module-nav-label">Variance Analysis</span>
    <a href="{{ route('variance-analysis.index') }}" class="module-nav-btn" aria-label="Go to variance analysis">
        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
    </a>
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
                            <div class="col-4">
                                <button id="runProjectionBtn" class="btn btn-dark w-100">Run</button>
                            </div>
                            <div class="col-4">
                                <button id="saveScenarioBtn" class="btn btn-outline-secondary w-100">Save</button>
                            </div>
                            <div class="col-4">
                                <button id="clearInputsBtn" class="btn btn-outline-secondary w-100" type="button">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="input-subcard mb-0">
                        <div class="mb-0">
                            <label class="form-label form-label-sm">Load Scenario</label>
                            <button id="openScenariosBtn" class="btn btn-outline-secondary w-100" type="button">Open Saved Scenarios</button>
                        </div>
                        <hr class="section-divider my-3">
                        <div class="mb-0">
                            <label class="form-label form-label-sm">Scenario Comparison</label>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <select id="compareScenarioA" class="form-select compact-input"></select>
                                </div>
                                <div class="col-6">
                                    <select id="compareScenarioB" class="form-select compact-input"></select>
                                </div>
                            </div>
                            <button id="compareScenariosBtn" class="btn btn-outline-secondary w-100" type="button">Compare</button>
                        </div>
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
                        <button class="projection-input-tab" id="tab-elr" data-bs-toggle="tab" data-bs-target="#pane-elr" type="button" role="tab" aria-controls="pane-elr" aria-selected="false" data-bs-title="ELR" data-bs-placement="top"><i class="fa-solid fa-piggy-bank"></i></button>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-scenario" role="tabpanel" aria-labelledby="tab-scenario" tabindex="0">
                            <div class="input-subcard mb-0">
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
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Employee EPF (%)</label>
                                        <input id="employeeEpfRatePercent" type="number" step="0.01" class="form-control compact-input" value="11.00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label form-label-sm">Employer EPF (%)</label>
                                        <input id="employerEpfRatePercent" type="number" step="0.01" class="form-control compact-input" value="13.00">
                                    </div>
                                </div>

                                <div class="table-responsive mb-3">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Probation</th>
                                            <th>Confirmed</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>Salary</td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">RM</span>
                                                    <input id="probationSalary" type="text" inputmode="decimal" class="form-control compact-input money-input" value="1800.00">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">RM</span>
                                                    <input id="confirmedSalary" type="text" inputmode="decimal" class="form-control compact-input money-input" value="2200.00">
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Employee EPF</td>
                                            <td class="text-end"><span id="probationEmployeeEpfAmount">RM 0.00</span></td>
                                            <td class="text-end"><span id="confirmedEmployeeEpfAmount">RM 0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td>Employer EPF</td>
                                            <td class="text-end"><span id="probationEmployerEpfAmount">RM 0.00</span></td>
                                            <td class="text-end"><span id="confirmedEmployerEpfAmount">RM 0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td>SOCSO (Act 4)</td>
                                            <td class="text-end"><span id="probationSocsoAmount">RM 0.00</span></td>
                                            <td class="text-end"><span id="confirmedSocsoAmount">RM 0.00</span></td>
                                        </tr>
                                        <tr>
                                            <td>EIS (Act 800)</td>
                                            <td class="text-end"><span id="probationEisAmount">RM 0.00</span></td>
                                            <td class="text-end"><span id="confirmedEisAmount">RM 0.00</span></td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="row g-2 mb-3">
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
                                <h2 class="section-subtitle">Budget Amounts</h2>
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
                                <div class="row g-2 mb-0">
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
                                <h2 class="section-subtitle d-flex justify-content-between align-items-center">BNPL Schedules
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
                                <h2 class="section-subtitle d-flex justify-content-between align-items-center">Events List
                                    <button id="addEventBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h2>
                                <hr class="section-divider">
                                <div class="table-responsive mb-0">
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
                                <h3 class="section-subtitle">Plan Details</h3>
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Note</label>
                                        <textarea id="elrNote" class="form-control compact-input" rows="2" placeholder=""></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label form-label-sm">Annual Interest Rate (%)</label>
                                        <input id="elrAnnualInterestRatePercent" type="number" step="0.01" min="0" max="100" class="form-control compact-input" value="0.00">
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check mt-1">
                                            <input id="elrCompoundInterestEnabled" class="form-check-input" type="checkbox">
                                            <label class="form-check-label" for="elrCompoundInterestEnabled">Enable compound interest</label>
                                        </div>
                                    </div>
                                </div>
                                <h3 class="section-subtitle d-flex justify-content-between align-items-center">ELR Schedules
                                    <button id="addElrScheduleBtn" type="button" class="btn btn-sm btn-outline-secondary">Add</button>
                                </h3>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr>
                                            <th>Start</th>
                                            <th>End</th>
                                            <th>Daily Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody id="elrScheduleRows"></tbody>
                                    </table>
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

        </div>
    </div>
</div>
<div id="statusMessage" class="small" role="status" aria-live="polite"></div>

<div class="modal fade" id="savedScenariosModal" tabindex="-1" aria-labelledby="savedScenariosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="savedScenariosModalLabel">Saved Scenarios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Scenario Name</th>
                            <th>Notes</th>
                            <th>Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="savedScenariosRows">
                        <tr><td colspan="4" class="text-center text-secondary py-3">No saved scenarios.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scenarioComparisonModal" tabindex="-1" aria-labelledby="scenarioComparisonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scenarioComparisonModalLabel">Scenario Comparison</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body mx-0 px-0">
                <div class="table-responsive mx-0 px-0">
                    <table class="table table-striped table-sm mb-0 mx-0 px-0 projection-table">
                        <colgroup>
                            <col span="6" style="width:16.6667%">
                        </colgroup>
                        <thead class="table-light sticky-top">
                        <tr>
                            <th>Scenario</th>
                            <th class="text-end">Final COH</th>
                            <th class="text-end">Final ELR</th>
                            <th class="text-end">Final EPF</th>
                            <th class="text-end">Lowest COH</th>
                            <th class="text-end">Highest COH</th>
                        </tr>
                        </thead>
                        <tbody id="comparisonRows">
                        <tr><td colspan="6" class="text-center text-secondary">No comparison data yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="confirmActionModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" id="confirmActionOkBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

@php
    $initialScenarios = $scenarios->map(fn ($scenario) => [
        'id' => $scenario->id,
        'name' => $scenario->name,
        'notes' => $scenario->notes,
        'created_at' => optional($scenario->created_at)->toDateTimeString(),
        'updated_at' => optional($scenario->updated_at)->toDateTimeString(),
    ])->values();
    $socsoBrackets = json_decode((string) file_get_contents(base_path('data/contribution-brackets/socso_act4_brackets.json')), true);
    $eisBrackets = json_decode((string) file_get_contents(base_path('data/contribution-brackets/eis_act800_brackets.json')), true);
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
        deleteScenarioBase: '{{ url('/projection/scenarios') }}',
        initialScenarios: @json($initialScenarios),
        statutoryBrackets: {
            socso: @json($socsoBrackets['brackets'] ?? []),
            eis: @json($eisBrackets['brackets'] ?? []),
        },
    };
</script>
<script src="{{ asset('js/projection-page.js') }}"></script>
</body>
</html>
