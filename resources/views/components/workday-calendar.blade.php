<?php

use App\Models\Workday;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public string $month;

    public string $calendarMonthLabel = '';

    public array $calendarCells = [];

    public function mount(): void
    {
        $this->month = now('Asia/Kuala_Lumpur')->format('Y-m');
        $this->buildCalendar();
    }

    public function goToPreviousMonth(): void
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month, 'Asia/Kuala_Lumpur')->startOfMonth();
        $this->month = $monthStart->copy()->subMonth()->format('Y-m');
        $this->buildCalendar();
    }

    public function goToNextMonth(): void
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month, 'Asia/Kuala_Lumpur')->startOfMonth();
        $this->month = $monthStart->copy()->addMonth()->format('Y-m');
        $this->buildCalendar();
    }

    public function cycleDayStatus(string $date): void
    {
        $selectedDate = Carbon::parse($date, 'Asia/Kuala_Lumpur')->startOfDay();

        $workday = Workday::query()->firstOrCreate(
            ['date' => $selectedDate->toDateString()],
            [
                'status' => $selectedDate->isWeekday() ? Workday::STATUS_WORKDAY : Workday::STATUS_HOLIDAY,
                'notes' => null,
            ]
        );

        $current = $this->normalizeStatus((string) ($workday->status ?? ''));
        $next = match ($current) {
            Workday::STATUS_WORKDAY => Workday::STATUS_ABSENCE,
            Workday::STATUS_ABSENCE => Workday::STATUS_HOLIDAY,
            default => Workday::STATUS_WORKDAY,
        };

        $workday->update([
            'status' => $next,
        ]);

        $this->buildCalendar();
        $this->dispatch('counter-updated');
    }

    private function buildCalendar(): void
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month, 'Asia/Kuala_Lumpur')->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $workdays = Workday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (Workday $workday) => $workday->date->toDateString());

        $cells = [];

        for ($i = 1; $i < $monthStart->dayOfWeekIso; $i++) {
            $cells[] = null;
        }

        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $workday = $workdays->get($dateKey);
            $status = $this->resolveStatus($workday, $cursor);

            $cells[] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'status' => $status,
                'status_label' => ucfirst($status),
                'button_class' => $this->buttonClass($status),
            ];

            $cursor->addDay();
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $this->calendarCells = $cells;
        $this->calendarMonthLabel = $monthStart->format('F Y');
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (in_array($status, [Workday::STATUS_WORKDAY, Workday::STATUS_ABSENCE, Workday::STATUS_HOLIDAY], true)) {
            return $status;
        }

        return Workday::STATUS_HOLIDAY;
    }

    private function resolveStatus(?Workday $workday, Carbon $date): string
    {
        if ($workday) {
            $status = $this->normalizeStatus((string) ($workday->status ?? ''));

            if (! empty($workday->status)) {
                return $status;
            }
        }

        return $date->isWeekday() ? Workday::STATUS_WORKDAY : Workday::STATUS_HOLIDAY;
    }

    private function buttonClass(string $status): string
    {
        return match ($status) {
            Workday::STATUS_WORKDAY => 'btn-success',
            Workday::STATUS_ABSENCE => 'btn-warning',
            default => 'btn-secondary',
        };
    }
};
?>

<div class="card data-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                wire:click="goToPreviousMonth"
                wire:loading.attr="disabled"
            >
                {{ \Carbon\Carbon::createFromFormat('Y-m', $month, 'Asia/Kuala_Lumpur')->startOfMonth()->subMonth()->format('F') }}
            </button>

            <h2 class="h5 mb-0">{{ $calendarMonthLabel }}</h2>

            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                wire:click="goToNextMonth"
                wire:loading.attr="disabled"
            >
                {{ \Carbon\Carbon::createFromFormat('Y-m', $month, 'Asia/Kuala_Lumpur')->startOfMonth()->addMonth()->format('F') }}
            </button>
        </div>

        <p class="text-muted small mb-3">Click a date to cycle status: Workday (green) -> Absence (yellow) -> Holiday (grey).</p>

        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center mb-0">
                <thead>
                <tr>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                    <th>Sun</th>
                </tr>
                </thead>
                <tbody>
                @foreach (array_chunk($calendarCells, 7) as $week)
                    <tr>
                        @foreach ($week as $cell)
                            @if ($cell)
                                <td class="p-1">
                                    <button
                                        type="button"
                                        class="btn btn-sm w-100 {{ $cell['button_class'] }}"
                                        wire:click="cycleDayStatus('{{ $cell['date'] }}')"
                                        wire:key="{{ $cell['date'] }}"
                                        wire:loading.attr="disabled"
                                        title="{{ \Carbon\Carbon::parse($cell['date'])->format('d/m/Y') }} - {{ $cell['status_label'] }}"
                                    >
                                        {{ $cell['day'] }}
                                    </button>
                                </td>
                            @else
                                <td class="bg-light"></td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
