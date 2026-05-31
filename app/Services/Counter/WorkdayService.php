<?php

namespace App\Services\Counter;

use App\Models\Workday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkdayService
{
    public function statusForDate(CarbonInterface $date): string
    {
        $record = Workday::query()
            ->whereDate('date', $date->toDateString())
            ->first();

        return $this->resolveStatus($record, $date);
    }

    public function isWorkday(CarbonInterface $date): bool
    {
        return $this->statusForDate($date) === Workday::STATUS_WORKDAY;
    }

    public function isScheduledWorkday(CarbonInterface $date): bool
    {
        $status = $this->statusForDate($date);

        return in_array($status, [Workday::STATUS_WORKDAY, Workday::STATUS_ABSENCE], true);
    }

    public function workdaysBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Workday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('status', Workday::STATUS_WORKDAY)
            ->orderBy('date')
            ->get();
    }

    public function countScheduledWorkdaysInMonth(CarbonInterface $monthDate): int
    {
        $monthStart = Carbon::parse($monthDate)->startOfMonth();
        $monthEnd = Carbon::parse($monthDate)->endOfMonth();

        $records = Workday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn (Workday $workday) => $workday->date->toDateString());

        $count = 0;
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $record = $records->get($cursor->toDateString());
            $status = $this->resolveStatus($record, $cursor);

            if (in_array($status, [Workday::STATUS_WORKDAY, Workday::STATUS_ABSENCE], true)) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }

    private function resolveStatus(?Workday $record, CarbonInterface $date): string
    {
        if ($record) {
            $status = strtolower((string) ($record->status ?? ''));

            if (in_array($status, [Workday::STATUS_WORKDAY, Workday::STATUS_ABSENCE, Workday::STATUS_HOLIDAY], true)) {
                return $status;
            }
        }

        return $date->isWeekday() ? Workday::STATUS_WORKDAY : Workday::STATUS_HOLIDAY;
    }
}
