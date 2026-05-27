<?php

namespace App\Services;

use App\Models\Workday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkdayService
{
    public function isWorkday(CarbonInterface $date): bool
    {
        $record = Workday::query()
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($record) {
            return (bool) $record->is_workday;
        }

        return $date->isWeekday();
    }

    public function workdaysBetween(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return Workday::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('is_workday', true)
            ->orderBy('date')
            ->get();
    }

    public function countWorkdaysInMonth(CarbonInterface $monthDate): int
    {
        $monthStart = Carbon::parse($monthDate)->startOfMonth();
        $monthEnd = Carbon::parse($monthDate)->endOfMonth();

        $configuredCount = Workday::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('is_workday', true)
            ->count();

        if ($configuredCount > 0) {
            return $configuredCount;
        }

        $count = 0;
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            if ($cursor->isWeekday()) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }
}
