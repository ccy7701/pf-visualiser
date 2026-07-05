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
    <div class="counter-stage" tabindex="0" aria-label="Actual cash on hand. Hover or focus to show actual cash plus this month's unpaid salary accrual.">
        <div class="counter-layer counter-layer-actual">
            <div id="actualCounterValue" class="counter-value">RM {{ number_format($snapshot['actual_counter'] ?? $snapshot['counter'], 2) }}</div>
        </div>
        <div class="counter-layer counter-layer-expected">
            <div id="expectedCounterValue" class="counter-value">RM {{ number_format(($snapshot['actual_counter'] ?? $snapshot['counter']) + ($snapshot['current_month_unpaid_accrual'] ?? $snapshot['accrued_salary'] ?? 0), 2) }}</div>
            <div id="incrementStatus" class="counter-status"></div>
        </div>
    </div>
</div>

@include('components.module-nav', ['current' => 'counter'])

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
