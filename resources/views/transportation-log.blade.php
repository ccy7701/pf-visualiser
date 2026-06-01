<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transportation Log</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/projection.css') }}" rel="stylesheet">
    <link href="{{ asset('css/transportation-log.css') }}" rel="stylesheet">
</head>
<body>
<div class="module-nav-dock module-nav-dock-right" aria-label="Counter navigation">
    <span class="module-nav-label">Counter</span>
    <a href="{{ route('counter') }}" class="module-nav-btn" aria-label="Back to counter">
        <i class="fa-solid fa-wallet" aria-hidden="true"></i>
    </a>
</div>

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
                        <button class="projection-input-tab active" id="tab-vehicle" data-bs-toggle="tab" data-bs-target="#pane-vehicle" type="button" role="tab" aria-controls="pane-vehicle" aria-selected="true" data-bs-title="Vehicle Profile" data-bs-placement="top"><i class="fa-solid fa-car"></i></button>
                        <button class="projection-input-tab" id="tab-fuel-entry" data-bs-toggle="tab" data-bs-target="#pane-fuel-entry" type="button" role="tab" aria-controls="pane-fuel-entry" aria-selected="false" data-bs-title="Refuel Log" data-bs-placement="top"><i class="fa-solid fa-gas-pump"></i></button>
                        <button class="projection-input-tab" id="tab-drive-entry" data-bs-toggle="tab" data-bs-target="#pane-drive-entry" type="button" role="tab" aria-controls="pane-drive-entry" aria-selected="false" data-bs-title="Drive Log" data-bs-placement="top"><i class="fa-solid fa-road"></i></button>
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
                                <button id="addFuelLogBtn" type="button" class="btn btn-dark w-100">Add</button>
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
                                        <label for="commuteDate" class="form-label form-label-sm">Date</label>
                                        <input id="commuteDate" type="text" class="form-control compact-input date-picker" placeholder="DD/MM/YYYY">
                                    </div>
                                    <div class="col-6">
                                        <label for="commuteTime" class="form-label form-label-sm">Time</label>
                                        <input id="commuteTime" type="time" class="form-control compact-input">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="commuteNote" class="form-label form-label-sm">Notes</label>
                                    <input id="commuteNote" type="text" class="form-control compact-input" placeholder="Optional">
                                </div>
                                <button id="addCommuteLogBtn" type="button" class="btn btn-dark w-100">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card mb-3">
                <div class="card-header">Monthly Summary</div>
                <div class="card-body">
                    <div class="row g-2" id="fuelDashboardCards"></div>
                </div>
            </div>

            <div class="card panel-card mb-3">
                <div class="card-header">Refuel Logs</div>
                <div class="card-body p-0">
                    <div class="results-wrap">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>Vehicle</th>
                                    <th class="text-end">Litres</th>
                                    <th class="text-end">Total (RM)</th>
                                </tr>
                                </thead>
                                <tbody id="fuelLogRows">
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-4">No refuel logs yet.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">Drive Logs</div>
                <div class="card-body p-0">
                    <div class="results-wrap">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>Vehicle</th>
                                    <th>Route</th>
                                    <th class="text-end">Distance</th>
                                    <th class="text-end">Fuel Used</th>
                                    <th class="text-end">Cost</th>
                                </tr>
                                </thead>
                                <tbody id="commuteLogRows">
                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-4">No drive logs yet.</td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('js/edge-nav.js') }}"></script>
<script src="{{ asset('js/transportation-log-page.js') }}"></script>
</body>
</html>
