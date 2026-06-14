<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personal Finance Counter</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="{{ asset('css/counter.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
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
    <div class="counter-stage" tabindex="0" aria-label="Actual cash on hand. Hover or focus to show expected cash on hand.">
        <div class="counter-layer counter-layer-actual">
            <div id="actualCounterValue" class="counter-value">RM {{ number_format($snapshot['actual_counter'] ?? $snapshot['counter'], 2) }}</div>
        </div>
        <div class="counter-layer counter-layer-expected">
            <div id="expectedCounterValue" class="counter-value">RM {{ number_format($snapshot['expected_counter'] ?? $snapshot['counter'], 2) }}</div>
            <div id="incrementStatus" class="counter-status"></div>
        </div>
    </div>
</div>

@include('components.module-nav', ['current' => 'counter', 'showThemeToggle' => true])

{{-- Backdrop ────────────────────────── --}}
<div class="popup-backdrop" id="popupBackdrop"></div>

{{-- FAB button ──────────────────────── --}}
<button class="fab-btn" id="fabBtn" type="button" aria-label="Open menu">
    <i class="fa-solid fa-bars" aria-hidden="true"></i>
</button>

{{-- Step 1: tab selector popup ──────── --}}
<div class="tab-selector-popup" id="tabSelector">
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
        <span class="title" id="popupTitle">Workday Calendar</span>
        <button class="btn-close-popup" id="btnClosePopup" type="button" aria-label="Close">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="content-popup-body" id="contentPopupBody">
        {{-- Workday Calendar content ──── --}}
        <div id="panel-calendar">
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
<script>
    window.counterPageConfig = {
        snapshot: @json($snapshot),
        snapshotUrl: @json(route('counter.snapshot')),
        theme: @json($theme),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/counter-page.js') }}"></script>
@livewireScripts
</body>
</html>
