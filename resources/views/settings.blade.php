<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings</title>
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
    <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'settings'])

<main class="container-fluid settings-page py-4 px-3 px-lg-5">
    <header class="mb-3">
        <div>
            <h1 class="h3 mb-1">Settings</h1>
            <p class="text-secondary mb-4">Configure the counter, workday calendar and salary schedules</p>
        </div>
    </header>

    <div class="row g-3 settings-layout">
        <div class="col-xl-4">
            <div class="card panel-card settings-nav-card">
                <div class="card-header">Configurations</div>
                <div class="card-body">
                    <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
                        <button class="settings-tab active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab" aria-controls="general-pane" aria-selected="true" aria-label="General settings">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                            <span>General</span>
                        </button>
                        <button class="settings-tab" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar-pane" type="button" role="tab" aria-controls="calendar-pane" aria-selected="false" aria-label="Workday calendar">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span>Workday Calendar</span>
                        </button>
                        <button class="settings-tab" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary-pane" type="button" role="tab" aria-controls="salary-pane" aria-selected="false" aria-label="Salary schedules">
                            <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
                            <span>Salary Schedules</span>
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <section class="card panel-card settings-content-card">
                <div class="card-body settings-content tab-content">
                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel" aria-labelledby="general-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">General</h2>
                            <p class="text-secondary small mb-0">Set the baseline used by your finance counter and manage its browser notification.</p>
                        </div>
                        <livewire:settings-manager />
                    </div>
                    <div class="tab-pane fade" id="calendar-pane" role="tabpanel" aria-labelledby="calendar-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">Workday calendar</h2>
                            <p class="text-secondary small mb-0">Mark working days, absences and holidays used by salary accrual calculations.</p>
                        </div>
                        <livewire:workday-calendar />
                    </div>
                    <div class="tab-pane fade" id="salary-pane" role="tabpanel" aria-labelledby="salary-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">Salary schedules</h2>
                            <p class="text-secondary small mb-0">Maintain salary periods so counter projections use the correct net pay.</p>
                        </div>
                        <livewire:salary-schedule-manager />
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
    window.settingsPageConfig = {
        theme: @json($theme),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/settings-page.js') }}"></script>
@livewireScripts
</body>
</html>
