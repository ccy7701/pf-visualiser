<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Log</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/transaction-log.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'transaction-log'])

<div class="container-fluid py-4 px-3 px-lg-5 transaction-log-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Transaction Log</h1>
            <p class="text-secondary mb-2">Record income and expenses, then review recent transaction activity</p>
        </div>
    </div>

    <div class="transaction-log-shell">
        <livewire:transaction-log />
    </div>
</div>

<script>
    window.counterPageConfig = {
        snapshot: @json($snapshot),
        snapshotUrl: @json(route('counter.snapshot')),
        theme: @json($theme),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/transaction-log-page.js') }}"></script>
@livewireScripts
</body>
</html>
