<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COH Projection</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
@include('components.module-nav', ['current' => 'projection'])

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
                        <button class="projection-input-tab active" id="tab-scenario" data-bs-toggle="tab" data-bs-target="#pane-scenario" type="button" role="tab" aria-controls="pane-scenario" aria-selected="true" aria-label="Starting Parameters" data-bs-title="Starting Parameters"><i class="fa-solid fa-calendar-days"></i></button>
                        <button class="projection-input-tab" id="tab-employment" data-bs-toggle="tab" data-bs-target="#pane-employment" type="button" role="tab" aria-controls="pane-employment" aria-selected="false" aria-label="Employment" data-bs-title="Employment"><i class="fa-solid fa-briefcase"></i></button>
                        <button class="projection-input-tab" id="tab-budget-profiles" data-bs-toggle="tab" data-bs-target="#pane-budget-profiles" type="button" role="tab" aria-controls="pane-budget-profiles" aria-selected="false" aria-label="Budget Profiles" data-bs-title="Budget Profiles"><i class="fa-solid fa-wallet"></i></button>
                        <button class="projection-input-tab" id="tab-monthly-budget" data-bs-toggle="tab" data-bs-target="#pane-monthly-budget" type="button" role="tab" aria-controls="pane-monthly-budget" aria-selected="false" aria-label="Monthly Budget Selection" data-bs-title="Monthly Budget Selection"><i class="fa-solid fa-list-check"></i></button>
                        <button class="projection-input-tab" id="tab-ptptn" data-bs-toggle="tab" data-bs-target="#pane-ptptn" type="button" role="tab" aria-controls="pane-ptptn" aria-selected="false" aria-label="PTPTN" data-bs-title="PTPTN"><i class="fa-solid fa-graduation-cap"></i></button>
                        <button class="projection-input-tab" id="tab-bnpl" data-bs-toggle="tab" data-bs-target="#pane-bnpl" type="button" role="tab" aria-controls="pane-bnpl" aria-selected="false" aria-label="BNPL" data-bs-title="BNPL"><i class="fa-solid fa-credit-card"></i></button>
                        <button class="projection-input-tab" id="tab-events" data-bs-toggle="tab" data-bs-target="#pane-events" type="button" role="tab" aria-controls="pane-events" aria-selected="false" aria-label="Events" data-bs-title="Events"><i class="fa-solid fa-calendar-plus"></i></button>
                        <button class="projection-input-tab" id="tab-elr" data-bs-toggle="tab" data-bs-target="#pane-elr" type="button" role="tab" aria-controls="pane-elr" aria-selected="false" aria-label="ELR" data-bs-title="ELR"><i class="fa-solid fa-piggy-bank"></i></button>
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
                                <input id="salaryScheduleEditingId" type="hidden" value="">
                                <div class="mb-2">
                                    <label for="salaryScheduleNote" class="form-label form-label-sm">Note</label>
                                    <input id="salaryScheduleNote" type="text" class="form-control compact-input" placeholder="Example: Confirmed salary">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="salaryScheduleFrom" class="form-label form-label-sm">From</label>
                                        <input id="salaryScheduleFrom" type="text" class="form-control compact-input month-input" value="{{ now('Asia/Kuala_Lumpur')->startOfMonth()->format('Y-m') }}">
                                    </div>
                                    <div class="col-6">
                                        <label for="salaryScheduleUntil" class="form-label form-label-sm">Until</label>
                                        <input id="salaryScheduleUntil" type="text" class="form-control compact-input month-input" placeholder="Ongoing">
                                    </div>
                                    <p class="text-secondary small mb-0">Schedules are inclusive month ranges. Leave Until blank for the current ongoing salary.</p>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <label for="salaryScheduleGross" class="form-label form-label-sm">Gross Salary (RM)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="salaryScheduleGross" type="text" inputmode="decimal" class="form-control compact-input money-input" value="0.00">
                                        </div>
                                    </div>
                                </div>
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
                                <div class="row g-2 mt-1 mb-3">
                                    <div class="col-12">
                                        <div class="form-check mt-1">
                                            <input id="salaryPaidInArrears" class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label" for="salaryPaidInArrears">Salary paid in arrears (full-month lag)</label>
                                        </div>
                                    </div>
                                </div>
                                <button id="saveSalaryScheduleBtn" type="button" class="btn btn-dark w-100">Add</button>
                            </div>

                            <div class="input-subcard mt-3 mb-0">
                                <h2 class="section-subtitle">Salary Schedules Added</h2>
                                <hr class="section-divider">
                                <div id="salaryScheduleListCards">
                                    <div class="text-center text-secondary py-3">No salary schedules added yet.</div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-budget-profiles" role="tabpanel" aria-labelledby="tab-budget-profiles" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">Budget Profiles</h2>
                                <hr class="section-divider">
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label form-label-sm">Profile Name</label>
                                        <input id="budgetProfileName" type="text" class="form-control form-control-sm" maxlength="120">
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button id="saveBudgetProfileBtn" type="button" class="btn btn-sm btn-dark">Add</button>
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button id="newBudgetProfileBtn" type="button" class="btn btn-sm btn-outline-secondary">New</button>
                                    </div>
                                </div>
                                <div class="table-responsive mb-0">
                                    <table class="table table-sm">
                                        <thead>
                                        <tr id="costAllocationHeaderRows"></tr>
                                        </thead>
                                        <tbody id="costAllocationRows"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="input-subcard mt-3 mb-0">
                                <h2 class="section-subtitle">Budget Profiles Added</h2>
                                <hr class="section-divider">
                                <div id="budgetPlanListCards">
                                    <div class="text-center text-secondary py-3">No budget profiles added yet.</div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-monthly-budget" role="tabpanel" aria-labelledby="tab-monthly-budget" tabindex="0">
                            <div class="input-subcard mb-0">
                                <h2 class="section-subtitle">Monthly Budget Selection</h2>
                                <hr class="section-divider">
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
                                <div class="small text-secondary">Final TFP</div>
                                <div id="summaryFinalTfp" class="fw-semibold">RM 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>Projection Chart</div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="chartType" class="form-label form-label-sm mb-0 fs-normal">Chart Type</label>
                        <select id="chartType" class="form-select form-select-sm" style="width: 20rem;">
                            <option value="balance_lines">COH / ELR / EPF Lines</option>
                            <option value="balance_stack">COH / ELR / EPF Stacked Bars</option>
                            <option value="tfp_line">TFP</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
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
                                    <col span="9" style="width:11.1111%">
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
                                    <th class="text-end">TFP</th>
                                </tr>
                                </thead>
                                <tbody id="projectionRows">
                                <tr>
                                    <td colspan="9" class="text-center text-secondary py-4">Run a projection to view results.</td>
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
                    <table class="table table-sm mb-0 saved-scenarios-table">
                        <thead class="table-light">
                        <tr>
                            <th>Scenario Name</th>
                            <th class="saved-scenarios-notes-col">Notes</th>
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
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/projection-page.js') }}"></script>
</body>
</html>
