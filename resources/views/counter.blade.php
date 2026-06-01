<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Counter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    @livewireStyles
    <link href="{{ asset('css/counter.css') }}" rel="stylesheet">
</head>
<body>

{{-- Flash toast ──────────────────── --}}
@if (session('status'))
    <div class="alert alert-success flash-toast text-center" id="flashToast">
        {{ session('status') }}
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger flash-toast" id="flashToast">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Main counter (always visible) ──── --}}
<div class="counter-fullscreen">
    {{-- <div class="counter-label">Current Counter</div> --}}
    <div id="counterValue" class="counter-value">RM {{ number_format($snapshot['counter'], 2) }}</div>

    {{-- Incrementing status indicator --}}
    <div id="incrementStatus" class="mt-2" style="font-size: 0.9rem; min-height: 1.2rem;"></div>
</div>

<div class="module-nav-dock module-nav-dock-left" aria-label="Fuel log navigation">
    <a href="{{ route('fuel-log.index') }}" class="module-nav-btn" aria-label="Go to fuel log">
        <i class="fa-solid fa-gas-pump" aria-hidden="true"></i>
    </a>
    <span class="module-nav-label">Fuel Log</span>
</div>

<div class="module-nav-dock module-nav-dock-right" aria-label="Projection navigation">
    <span class="module-nav-label">Projection</span>
    <a href="{{ route('projection.index') }}" class="module-nav-btn" aria-label="Go to projection">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
    </a>
</div>

{{-- Backdrop ────────────────────────── --}}
<div class="popup-backdrop" id="popupBackdrop"></div>

{{-- FAB button ──────────────────────── --}}
<button class="fab-btn" id="fabBtn" type="button" aria-label="Open menu">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
</button>

{{-- Step 1: tab selector popup ──────── --}}
<div class="tab-selector-popup" id="tabSelector">
    <button type="button" data-tab="transactions">Transaction Log</button>
    <button type="button" data-tab="calendar">Workday Calendar</button>
    <button type="button" data-tab="schedules">Salary Schedules</button>
    <button type="button" data-tab="settings">Settings</button>
</div>

{{-- Step 2: content popup ────────────── --}}
<div class="content-popup" id="contentPopup">
    <div class="content-popup-header">
        <button class="btn-back" id="btnBack" type="button" aria-label="Back">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </button>
        <span class="title" id="popupTitle">Transaction Log</span>
        <button class="btn-close-popup" id="btnClosePopup" type="button" aria-label="Close">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="content-popup-body" id="contentPopupBody">
        {{-- Transaction Log content ──── --}}
        <div id="panel-transactions">
            <livewire:transaction-log />
        </div>

        {{-- Workday Calendar content ──── --}}
        <div id="panel-calendar" class="d-none">
            <livewire:workday-calendar />
        </div>

        {{-- Salary Schedule content ──── --}}
        <div id="panel-schedules" class="d-none">
            <livewire:salary-schedule-manager />
        </div>

        {{-- Settings content ──── --}}
        <div id="panel-settings" class="d-none">
            <livewire:settings-manager />
        </div>
    </div>
</div>

{{-- Scripts ──────────────────────────── --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('js/edge-nav.js') }}"></script>
<script>
    window.counterPageConfig = {
        snapshot: @json($snapshot),
        snapshotUrl: @json(route('counter.snapshot')),
        theme: @json($theme),
    };
</script>
<script src="{{ asset('js/counter-page.js') }}"></script>
@livewireScripts
</body>
</html>
