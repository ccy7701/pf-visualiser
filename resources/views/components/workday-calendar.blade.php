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

    public function toggleWorkday(string $date): void
    {
        $selectedDate = Carbon::parse($date, 'Asia/Kuala_Lumpur')->startOfDay();

        $workday = Workday::query()->firstOrCreate(
            ['date' => $selectedDate->toDateString()],
            [
                'is_workday' => $selectedDate->isWeekday(),
                'notes' => null,
            ]
        );

        $workday->update([
            'is_workday' => ! $workday->is_workday,
        ]);

        $this->buildCalendar();
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

            $cells[] = [
                'date' => $dateKey,
                'day' => $cursor->day,
                'is_workday' => $workday ? (bool) $workday->is_workday : $cursor->isWeekday(),
            ];

            $cursor->addDay();
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $this->calendarCells = $cells;
        $this->calendarMonthLabel = $monthStart->format('F Y');
    }
};
?>

<div class="card data-card">
    <div class="card-body p-4">
        <h2 class="h5 mb-1">Workday Calendar</h2>
        <p class="text-muted small mb-3">{{ $calendarMonthLabel }} · Click a date to toggle workday (green) or non-workday (grey).</p>

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
                                        class="btn btn-sm w-100 {{ $cell['is_workday'] ? 'btn-success' : 'btn-secondary' }}"
                                        wire:click="toggleWorkday('{{ $cell['date'] }}')"
                                        wire:key="{{ $cell['date'] }}"
                                        wire:loading.attr="disabled"
                                        title="{{ \Carbon\Carbon::parse($cell['date'])->format('d/m/Y') }}"
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