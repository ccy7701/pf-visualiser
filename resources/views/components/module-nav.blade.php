@php
    $pages = [
        'transportation-log' => [
            'label' => 'Transportation Log',
            'icon' => 'fa-road',
            'route' => 'transportation-log.index',
        ],
        'counter' => [
            'label' => 'Counter',
            'icon' => 'fa-wallet',
            'route' => 'counter',
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
            'route' => null,
        ],
    ];

    $currentPage = $pages[$current] ?? $pages['counter'];
@endphp

<nav class="module-nav-radial" aria-label="Module navigation">
    <div class="module-nav-current" aria-current="page">
        <span class="module-nav-label">{{ $currentPage['label'] }}</span>
        <span class="module-nav-btn module-nav-btn-current">
            <i class="fa-solid {{ $currentPage['icon'] }}" aria-hidden="true"></i>
        </span>
    </div>

    @php($navOption = 0)
    @foreach ($pages as $key => $page)
        @continue($key === $current)
        @php($navOption++)

        <div class="module-nav-option module-nav-option-{{ $navOption }}">
            <span class="module-nav-label">{{ $page['label'] }}</span>
            @if ($page['route'])
                <a href="{{ route($page['route']) }}" class="module-nav-btn" aria-label="Go to {{ strtolower($page['label']) }}">
                    <i class="fa-solid {{ $page['icon'] }}" aria-hidden="true"></i>
                </a>
            @else
                <button class="module-nav-btn module-nav-btn-placeholder" type="button" disabled aria-label="{{ $page['label'] }} placeholder">
                    <i class="fa-solid {{ $page['icon'] }}" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    @endforeach
</nav>
