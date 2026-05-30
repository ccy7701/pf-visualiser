<?php

namespace App\Services;

use App\Models\SalarySchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SalaryAccrualService
{
    private const WORK_SECONDS_PER_DAY = 28800;

    private const WORK_WINDOWS = [
        ['08:30', '12:30'],
        ['13:30', '17:30'],
    ];

    public function __construct(private readonly WorkdayService $workdayService)
    {
    }

    public function computeAccruedSalary(CarbonInterface $asOf): array
    {
        $firstSchedule = SalarySchedule::query()->orderBy('effective_from')->first();

        if (! $firstSchedule) {
            return [
                'accrued_salary' => 0.0,
                'increment_per_second' => 0.0,
            ];
        }

        $accrualStart = Carbon::parse($firstSchedule->effective_from)->startOfDay();

        if ($asOf->lte($accrualStart)) {
            return [
                'accrued_salary' => 0.0,
                'increment_per_second' => 0.0,
            ];
        }

        $totalAccrued = 0.0;
        $cursor = $accrualStart->copy();
        $cache = [];

        while ($cursor->lte($asOf)) {
            $schedule = $this->scheduleForDate($cursor);

            if ($schedule) {
                $monthCacheKey = $schedule->id.'-'.$cursor->format('Y-m');
                $scheduledWorkdays = $cache[$monthCacheKey] ??= $this->workdayService->countScheduledWorkdaysInMonth($cursor);

                if ($scheduledWorkdays > 0 && $this->workdayService->isWorkday($cursor)) {
                    $dailyAccruedSalary = (float) $schedule->monthly_net_salary / $scheduledWorkdays;
                    $eligibleSeconds = $this->eligibleWorkingSecondsForDay($cursor, $accrualStart, $asOf);
                    $totalAccrued += $dailyAccruedSalary * ($eligibleSeconds / self::WORK_SECONDS_PER_DAY);
                }
            }

            $cursor->addDay()->startOfDay();
        }

        return [
            'accrued_salary' => $totalAccrued,
            'increment_per_second' => $this->currentIncrementPerSecond($asOf),
        ];
    }

    public function currentIncrementPerSecond(CarbonInterface $at): float
    {
        $schedule = $this->scheduleForDate($at);

        if (! $schedule || ! $this->workdayService->isWorkday($at) || ! $this->isWithinWorkingWindow($at)) {
            return 0.0;
        }

        $scheduledWorkdays = $this->workdayService->countScheduledWorkdaysInMonth($at);

        if ($scheduledWorkdays === 0) {
            return 0.0;
        }

        $dailyAccruedSalary = (float) $schedule->monthly_net_salary / $scheduledWorkdays;

        return $dailyAccruedSalary / self::WORK_SECONDS_PER_DAY;
    }

    private function scheduleForDate(CarbonInterface $date): ?SalarySchedule
    {
        return SalarySchedule::query()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private function eligibleWorkingSecondsForDay(CarbonInterface $day, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = Carbon::parse($day)->endOfDay();

        $start = $periodStart->greaterThan($dayStart) ? Carbon::parse($periodStart) : $dayStart;
        $end = $periodEnd->lessThan($dayEnd) ? Carbon::parse($periodEnd) : $dayEnd;

        if ($end->lte($start)) {
            return 0;
        }

        $eligibleSeconds = 0;

        foreach (self::WORK_WINDOWS as [$windowStartTime, $windowEndTime]) {
            $windowStart = Carbon::parse($day)->setTimeFromTimeString($windowStartTime);
            $windowEnd = Carbon::parse($day)->setTimeFromTimeString($windowEndTime);

            $segmentStart = $start->greaterThan($windowStart) ? $start : $windowStart;
            $segmentEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;

            if ($segmentEnd->gt($segmentStart)) {
                $eligibleSeconds += (int) $segmentStart->diffInSeconds($segmentEnd);
            }
        }

        return $eligibleSeconds;
    }

    private function isWithinWorkingWindow(CarbonInterface $at): bool
    {
        foreach (self::WORK_WINDOWS as [$windowStartTime, $windowEndTime]) {
            $windowStart = Carbon::parse($at)->setTimeFromTimeString($windowStartTime);
            $windowEnd = Carbon::parse($at)->setTimeFromTimeString($windowEndTime);

            if ($at->gte($windowStart) && $at->lt($windowEnd)) {
                return true;
            }
        }

        return false;
    }
}
