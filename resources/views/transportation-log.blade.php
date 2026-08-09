<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transportation Log</title>
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
    <link href="{{ asset('css/transportation-log.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'transportation-log'])

<div class="container-fluid py-4 px-3 px-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Transportation Log</h1>
            <p class="text-secondary mb-2">Track refuelling, estimate drive cost, and compare spending</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card panel-card">
                <div class="card-header">Inputs</div>
                <div class="card-body">
                    <div class="projection-input-tabs nav" id="fuelInputTabs" role="tablist">
                        <button class="projection-input-tab active" id="tab-vehicle" data-bs-toggle="tab" data-bs-target="#pane-vehicle" type="button" role="tab" aria-controls="pane-vehicle" aria-selected="true" aria-label="Vehicle Profile" data-bs-title="Vehicle Profile"><i class="fa-solid fa-car"></i></button>
                        <button class="projection-input-tab" id="tab-fuel-entry" data-bs-toggle="tab" data-bs-target="#pane-fuel-entry" type="button" role="tab" aria-controls="pane-fuel-entry" aria-selected="false" aria-label="Refuel Log" data-bs-title="Refuel Log"><i class="fa-solid fa-gas-pump"></i></button>
                        <button class="projection-input-tab" id="tab-drive-entry" data-bs-toggle="tab" data-bs-target="#pane-drive-entry" type="button" role="tab" aria-controls="pane-drive-entry" aria-selected="false" aria-label="Drive Log" data-bs-title="Drive Log"><i class="fa-solid fa-road"></i></button>
                        <button class="projection-input-tab" id="tab-parking-entry" data-bs-toggle="tab" data-bs-target="#pane-parking-entry" type="button" role="tab" aria-controls="pane-parking-entry" aria-selected="false" aria-label="Parking Log" data-bs-title="Parking Log"><i class="fa-solid fa-square-parking"></i></button>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="pane-vehicle" role="tabpanel" aria-labelledby="tab-vehicle" tabindex="0">
                            <div class="input-subcard mb-0">
                                <div class="mb-2">
                                    <label for="vehicleName" class="form-label form-label-sm">Vehicle Name</label>
                                    <input id="vehicleName" type="text" class="form-control compact-input" placeholder="Example: Myvi">
                                </div>
                                <div class="mb-2">
                                    <label for="vehicleDescription" class="form-label form-label-sm">Description</label>
                                    <input id="vehicleDescription" type="text" class="form-control compact-input" placeholder="Optional details">
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="tankCapacityL" class="form-label form-label-sm">Tank Capacity (L)</label>
                                        <input id="tankCapacityL" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label for="consumptionUnitDefault" class="form-label form-label-sm">Consumption Unit</label>
                                        <select id="consumptionUnitDefault" class="form-select compact-input">
                                            <option value="L_PER_100KM">L/100km</option>
                                            <option value="KM_PER_L">km/L</option>
                                        </select>
                                    </div>
                                </div>
                                <button id="saveVehicleBtn" type="button" class="btn btn-dark w-100">Save</button>
                            </div>
                            <div class="input-subcard mt-3 mb-0">
                                <h2 class="section-subtitle">Vehicles Added</h2>
                                <hr class="section-divider">
                                <div id="vehicleListCards">
                                    <div class="text-center text-secondary py-3">No vehicles added yet.</div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-fuel-entry" role="tabpanel" aria-labelledby="tab-fuel-entry" tabindex="0">
                            <div class="input-subcard mb-0">
                                <div class="mb-2">
                                    <label for="fuelVehicleId" class="form-label form-label-sm">Vehicle</label>
                                    <select id="fuelVehicleId" class="form-select compact-input"></select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="fuelOdometerKm" class="form-label form-label-sm">Odometer (km)</label>
                                        <input id="fuelOdometerKm" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label for="fuelLitres" class="form-label form-label-sm">Fuel (L)</label>
                                        <input id="fuelLitres" type="number" min="0" step="0.001" class="form-control compact-input" value="0">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="fuelPriceMode" class="form-label form-label-sm">Fuel Price Type</label>
                                        <select id="fuelPriceMode" class="form-select compact-input">
                                            <option value="budi95">BUDI95</option>
                                            <option value="ron95">RON95</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label for="fuelPricePerLitre" class="form-label form-label-sm">Price/L</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="fuelPricePerLitre" type="number" min="0" step="0.001" class="form-control compact-input" value="1.99">
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="fuelTotalAmount" class="form-label form-label-sm">Total</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">RM</span>
                                            <input id="fuelTotalAmount" type="number" min="0" step="0.01" class="form-control compact-input" value="0" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="fuelledAtDate" class="form-label form-label-sm">Date</label>
                                        <input id="fuelledAtDate" type="text" class="form-control compact-input date-picker" placeholder="DD/MM/YYYY">
                                    </div>
                                    <div class="col-6">
                                        <label for="fuelledAtTime" class="form-label form-label-sm">Time</label>
                                        <input id="fuelledAtTime" type="time" class="form-control compact-input">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label for="fuelLocation" class="form-label form-label-sm">Location</label>
                                    <input id="fuelLocation" type="text" class="form-control compact-input" placeholder="Optional">
                                </div>
                                <div class="mb-3">
                                    <label for="fuelNote" class="form-label form-label-sm">Notes</label>
                                    <input id="fuelNote" type="text" class="form-control compact-input" placeholder="Optional">
                                </div>
                                <div class="row g-2">
                                    <div class="col-12" id="fuelAddButtonWrap">
                                        <button id="addFuelLogBtn" type="button" class="btn btn-dark w-100">Add</button>
                                    </div>
                                    <div class="col-6 d-none" id="fuelEditButtonWrap">
                                        <button id="editFuelLogBtn" type="button" class="btn btn-dark w-100">Edit</button>
                                    </div>
                                    <div class="col-6 d-none" id="fuelDeleteButtonWrap">
                                        <button id="deleteFuelLogBtn" type="button" class="btn btn-outline-danger w-100">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-drive-entry" role="tabpanel" aria-labelledby="tab-drive-entry" tabindex="0">
                            <div class="input-subcard mb-0">
                                <div class="mb-2">
                                    <label for="commuteVehicleId" class="form-label form-label-sm">Vehicle</label>
                                    <select id="commuteVehicleId" class="form-select compact-input"></select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label for="commuteType" class="form-label form-label-sm">Drive Type</label>
                                        <select id="commuteType" class="form-select compact-input">
                                            <option value="work_commute">Work Commute</option>
                                            <option value="personal_drive">Personal Drive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteOrigin" class="form-label form-label-sm">Origin</label>
                                        <input id="commuteOrigin" type="text" class="form-control compact-input" value="Home">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteDestination" class="form-label form-label-sm">Destination</label>
                                        <input id="commuteDestination" type="text" class="form-control compact-input" value="Work">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteDistanceKm" class="form-label form-label-sm">Distance (km)</label>
                                        <input id="commuteDistanceKm" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteConsumptionValue" class="form-label form-label-sm">Mileage (L/100km)</label>
                                        <input id="commuteConsumptionValue" type="number" min="0" step="0.0001" class="form-control compact-input" value="0">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteFinalOdometerKm" class="form-label form-label-sm">Final Odometer Reading (km)</label>
                                        <input id="commuteFinalOdometerKm" type="number" min="0" class="form-control compact-input" value="0">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteDate" class="form-label form-label-sm">Start Date</label>
                                        <input id="commuteDate" type="text" class="form-control compact-input date-picker" placeholder="DD/MM/YYYY">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteTime" class="form-label form-label-sm">Start Time</label>
                                        <input id="commuteTime" type="time" class="form-control compact-input">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteEndDate" class="form-label form-label-sm">End Date</label>
                                        <input id="commuteEndDate" type="text" class="form-control compact-input date-picker" placeholder="DD/MM/YYYY">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteEndTime" class="form-label form-label-sm">End Time</label>
                                        <input id="commuteEndTime" type="time" class="form-control compact-input">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="commuteAverageSpeedKmh" class="form-label form-label-sm">Avg Speed (km/h)</label>
                                        <input id="commuteAverageSpeedKmh" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteTopSpeedKmh" class="form-label form-label-sm">Top Speed (km/h)</label>
                                        <input id="commuteTopSpeedKmh" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="commuteNote" class="form-label form-label-sm">Notes</label>
                                    <input id="commuteNote" type="text" class="form-control compact-input" placeholder="Optional">
                                </div>
                                <div class="row g-2">
                                    <div class="col-12" id="driveAddButtonWrap">
                                        <button id="addCommuteLogBtn" type="button" class="btn btn-dark w-100">Add</button>
                                    </div>
                                    <div class="col-6 d-none" id="driveEditButtonWrap">
                                        <button id="editCommuteLogBtn" type="button" class="btn btn-dark w-100">Edit</button>
                                    </div>
                                    <div class="col-6 d-none" id="driveDeleteButtonWrap">
                                        <button id="deleteCommuteLogBtn" type="button" class="btn btn-outline-danger w-100">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-parking-entry" role="tabpanel" aria-labelledby="tab-parking-entry" tabindex="0">
                            <div class="input-subcard mb-0">
                                <div class="row g-2 mb-2">
                                    <div class="col-12">
                                        <label for="parkingType" class="form-label form-label-sm">Parking Type</label>
                                        <select id="parkingType" class="form-select compact-input">
                                            <option value="casual">Casual Parking</option>
                                            <option value="monthly_pass">Monthly Pass</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label for="parkingLocation" class="form-label form-label-sm">Location</label>
                                    <input id="parkingLocation" type="text" class="form-control compact-input" placeholder="e.g. Mall">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="parkingDate" class="form-label form-label-sm">Date</label>
                                        <input id="parkingDate" type="text" class="form-control compact-input date-picker" placeholder="DD/MM/YYYY">
                                    </div>
                                    <div class="col-6" id="parkingBillingMonthWrap">
                                        <label for="parkingBillingMonth" class="form-label form-label-sm">Pass Month</label>
                                        <input id="parkingBillingMonth" type="month" class="form-control compact-input">
                                    </div>
                                </div>
                                <div class="row g-2 mb-2" id="parkingHourWrap">
                                    <div class="col-6">
                                        <label for="parkingStartHour" class="form-label form-label-sm">Start Hour</label>
                                        <select id="parkingStartHour" class="form-select compact-input"></select>
                                    </div>
                                    <div class="col-6">
                                        <label for="parkingEndHour" class="form-label form-label-sm">End Hour</label>
                                        <select id="parkingEndHour" class="form-select compact-input"></select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label for="parkingTotalAmount" class="form-label form-label-sm">Cost</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">RM</span>
                                        <input id="parkingTotalAmount" type="number" min="0" step="0.01" class="form-control compact-input" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="parkingNote" class="form-label form-label-sm">Notes</label>
                                    <input id="parkingNote" type="text" class="form-control compact-input" placeholder="Optional">
                                </div>
                                <div class="row g-2">
                                    <div class="col-12" id="parkingAddButtonWrap">
                                        <button id="addParkingLogBtn" type="button" class="btn btn-dark w-100">Add</button>
                                    </div>
                                    <div class="col-6 d-none" id="parkingEditButtonWrap">
                                        <button id="editParkingLogBtn" type="button" class="btn btn-dark w-100">Edit</button>
                                    </div>
                                    <div class="col-6 d-none" id="parkingDeleteButtonWrap">
                                        <button id="deleteParkingLogBtn" type="button" class="btn btn-outline-danger w-100">Delete</button>
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
                <div class="card-header transportation-summary-shell-header">
                    <div class="summary-title-actions">
                        <div id="transportationSummaryTitle">Summary for {{ now()->format('F Y') }}</div>
                        <button id="transportationSummaryExport" class="summary-export-button" type="button" aria-label="Export current transportation summary" title="Export current selection">
                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                            <span>Export</span>
                        </button>
                    </div>
                    <div class="summary-period-controls">
                        <div class="summary-period-segmented" id="transportationSummaryPeriod" role="group" aria-label="Summary period">
                            <button type="button" class="summary-period-option active" data-summary-period="monthly">Monthly</button>
                            <button type="button" class="summary-period-option" data-summary-period="weekly">Weekly</button>
                            <button type="button" class="summary-period-option" data-summary-period="since_refuel">Since Last Refuel</button>
                            <button type="button" class="summary-period-option" data-summary-period="custom">Period</button>
                        </div>
                        <div id="transportationSummaryCustomPeriod" class="summary-period-selection" hidden>
                            <label class="visually-hidden" for="transportationSummaryStartDate">Period start date</label>
                            <input id="transportationSummaryStartDate" class="form-control form-control-sm" type="date">
                            <span aria-hidden="true">to</span>
                            <label class="visually-hidden" for="transportationSummaryEndDate">Period end date</label>
                            <input id="transportationSummaryEndDate" class="form-control form-control-sm" type="date">
                        </div>
                        <div class="summary-period-shift" id="transportationSummaryShift" role="group" aria-label="Navigate summary period">
                            <button type="button" class="summary-period-shift-btn" data-summary-shift="-1" aria-label="Previous summary period">&lt;</button>
                            <button type="button" class="summary-period-shift-btn" data-summary-shift="1" aria-label="Next summary period">&gt;</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2" id="fuelDashboardCards"></div>

                    <div id="refuelLogsSection">
                        <h2 class="section-subtitle my-3">Refuel Logs</h2>
                        <div class="results-wrap transportation-section-table">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm mb-0 projection-table">
                                    <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Date &amp; Time</th>
                                        <th>Vehicle</th>
                                        <th>Location</th>
                                        <th class="text-end">Litres</th>
                                        <th class="text-end">Total (RM)</th>
                                    </tr>
                                    </thead>
                                    <tbody id="fuelLogRows">
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-4">No refuel logs yet.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <h2 class="section-subtitle my-3">Drive Logs</h2>
                    <div class="results-wrap transportation-section-table transportation-drive-logs-table">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Trip Timing</th>
                                    <th>Vehicle</th>
                                    <th>Route</th>
                                    <th>Drive Type</th>
                                    <th class="text-end">Distance</th>
                                    <th class="text-end">Fuel Used</th>
                                    <th class="text-end">Cost</th>
                                </tr>
                                </thead>
                                <tbody id="commuteLogRows">
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">No drive logs yet.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <h2 class="section-subtitle my-3">Parking Logs</h2>
                    <div class="results-wrap transportation-section-table">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Period</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Notes</th>
                                    <th class="text-end">Cost (RM)</th>
                                </tr>
                                </thead>
                                <tbody id="parkingLogRows">
                                <tr>
                                    <td colspan="5" class="text-center text-secondary py-4">No parking logs yet.</td>
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

<script>
    window.transportationLogConfig = {
        snapshotEndpoint: '{{ route('transportation-log.snapshot') }}',
        exportEndpoint: '{{ route('transportation-log.export') }}',
        vehiclesEndpoint: '{{ route('transportation-log.vehicles.store') }}',
        fuelLogsEndpoint: '{{ route('transportation-log.fuel-logs.store') }}',
        fuelLogsBaseUrl: '{{ url('/transportation-log/fuel-logs') }}',
        commuteLogsEndpoint: '{{ route('transportation-log.commute-logs.store') }}',
        commuteLogsBaseUrl: '{{ url('/transportation-log/commute-logs') }}',
        parkingLogsEndpoint: '{{ route('transportation-log.parking-logs.store') }}',
        parkingLogsBaseUrl: '{{ url('/transportation-log/parking-logs') }}',
        vehiclesBaseUrl: '{{ url('/transportation-log/vehicles') }}',
        vehicleBrandLogoBaseUrl: '{{ asset('images/vehicle-brands') }}',
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/transportation-log-page.js') }}"></script>
@livewireScripts
</body>
</html>
