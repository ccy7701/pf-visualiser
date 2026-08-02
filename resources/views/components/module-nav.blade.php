@php
    $showThemeToggle = $showThemeToggle ?? true;

    $pages = [
        'counter' => [
            'label' => 'Counter',
            'icon' => 'fa-wallet',
            'route' => 'counter',
        ],
        'transaction-log' => [
            'label' => 'Transaction Log',
            'icon' => 'fa-receipt',
            'route' => 'transaction-log.index',
        ],
        'projection' => [
            'label' => 'Projection',
            'icon' => 'fa-chart-line',
            'route' => 'projection.index',
        ],
        'variance-analysis' => [
            'label' => 'Variance Analysis',
            'icon' => 'fa-scale-balanced',
            'route' => 'variance-analysis.index',
        ],
        'history' => [
            'label' => 'History',
            'icon' => 'fa-clock-rotate-left',
            'route' => 'history.index',
        ],
        'transportation-log' => [
            'label' => 'Transportation',
            'icon' => 'fa-road',
            'route' => 'transportation-log.index',
        ],
        'prompt-studio' => [
            'label' => 'Prompt Studio',
            'icon' => 'fa-file-pen',
            'route' => 'prompt-studio.index',
        ],
    ];
@endphp

<nav class="module-nav-stack" aria-label="Module navigation">
    @foreach ($pages as $key => $page)
        @php($isCurrent = $key === $current)
        <a
            href="{{ route($page['route']) }}"
            class="module-nav-row {{ $isCurrent ? 'is-current' : '' }}"
            aria-label="Go to {{ strtolower($page['label']) }}"
            @if($isCurrent) aria-current="page" @endif
        >
            <span class="module-nav-label">{{ $page['label'] }}</span>
            <span class="module-nav-btn">
                <i class="fa-solid {{ $page['icon'] }}" aria-hidden="true"></i>
            </span>
        </a>
    @endforeach

    @if ($showThemeToggle)
        <livewire:theme-nav-toggle />
    @endif

    @php($isSettings = $current === 'settings')
    <a
        href="{{ route('settings.index') }}"
        class="module-nav-row module-nav-settings-row {{ $isSettings ? 'is-current' : '' }}"
        aria-label="Go to settings"
        @if($isSettings) aria-current="page" @endif
    >
        <span class="module-nav-label">Settings</span>
        <span class="module-nav-btn">
            <i class="fa-solid fa-cog" aria-hidden="true"></i>
        </span>
    </a>
</nav>
