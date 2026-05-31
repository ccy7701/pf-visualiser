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
</head>
<body>
<div class="module-nav-dock" aria-label="Projection navigation">
    <a href="{{ route('projection.index') }}" class="module-nav-btn" aria-label="Back to projection">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
    </a>
    <span class="module-nav-label">Projection</span>
</div>

<div class="container-fluid py-4 px-3 px-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Variance Analysis</h1>
            <p class="text-secondary mb-2">Load a saved projection scenario and key in actual EOTM values for variance tracking.</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card panel-card mb-3">
                <div class="card-header">Scenario</div>
                <div class="card-body">
                    <div class="input-subcard mb-0">
                        <div class="mb-2">
                            <label for="scenarioSelect" class="form-label form-label-sm">Saved Scenario</label>
                            <select id="scenarioSelect" class="form-select compact-input"></select>
                        </div>
                        <div class="d-grid gap-2">
                            <button id="loadScenarioBtn" class="btn btn-dark" type="button">Load Scenario</button>
                            <button id="saveActualsBtn" class="btn btn-outline-secondary" type="button" disabled>Save Actual Values</button>
                        </div>
                        <hr class="section-divider my-3">
                        <div class="small text-secondary">
                            <div><strong>Loaded:</strong> <span id="loadedScenarioName">-</span></div>
                            <div><strong>Last Updated:</strong> <span id="loadedScenarioUpdatedAt">-</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card panel-card">
                <div class="card-header">Legend</div>
                <div class="card-body">
                    <div class="input-subcard mb-0 small text-secondary">
                        <div><strong>Variance = Actual - Projected</strong></div>
                        <div>Positive values indicate actual is above projection.</div>
                        <div>Negative values indicate actual is below projection.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card panel-card">
                <div class="card-header">Monthly Comparison</div>
                <div class="card-body p-0">
                    <div class="results-wrap">
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0 projection-table">
                                <thead class="table-light sticky-top">
                                <tr>
                                    <th>Month</th>
                                    <th class="text-end">Proj COH</th>
                                    <th class="text-end">Actual COH</th>
                                    <th class="text-end">Var COH</th>
                                    <th class="text-end">Proj ELR</th>
                                    <th class="text-end">Actual ELR</th>
                                    <th class="text-end">Var ELR</th>
                                    <th class="text-end">Proj EPF</th>
                                    <th class="text-end">Actual EPF</th>
                                    <th class="text-end">Var EPF</th>
                                </tr>
                                </thead>
                                <tbody id="planActualRows">
                                <tr>
                                    <td colspan="10" class="text-center text-secondary py-4">Load a scenario to begin tracking actual values.</td>
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
@endphp

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/edge-nav.js') }}"></script>
<script>
    window.varianceAnalysisConfig = {
        initialScenarios: @json($initialScenarios),
        showScenarioBase: '{{ url('/variance-analysis/scenarios') }}',
        saveActualsBase: '{{ url('/variance-analysis/scenarios') }}',
    };
</script>
<script src="{{ asset('js/variance-analysis-page.js') }}"></script>
</body>
</html>
