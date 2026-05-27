<?php

namespace App\Services;

use App\Models\SalarySchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SalaryAccrualService
{
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
                'minute_rate' => 0.0,
            ];
        }

        $accrualStart = Carbon::parse($firstSchedule->effective_from)->startOfDay();

        if ($asOf->lte($accrualStart)) {
            return [
                'accrued_salary' => 0.0,
                'minute_rate' => 0.0,
            ];
        }

        $totalAccrued = 0.0;
        $cursor = $accrualStart->copy();
        $cache = [];

        while ($cursor->lte($asOf)) {
            $schedule = $this->scheduleForDate($cursor);

            if ($schedule && $this->workdayService->isWorkday($cursor)) {
                $monthCacheKey = $schedule->id.'-'.$cursor->format('Y-m');
                $workdaysInMonth = $cache[$monthCacheKey] ??= $this->workdayService->countWorkdaysInMonth($cursor);

                if ($workdaysInMonth > 0) {
                    $minuteRate = (float) $schedule->monthly_net_salary / ($workdaysInMonth * $this->workMinutesPerDay());
                    $minutes = $this->eligibleMinutesForDay($cursor, $accrualStart, $asOf);
                    $totalAccrued += $minuteRate * $minutes;
                }
            }

            $cursor->addDay()->startOfDay();
        }

        return [
            'accrued_salary' => round($totalAccrued, 2),
            'minute_rate' => $this->currentMinuteRate($asOf),
        ];
    }

    public function currentMinuteRate(CarbonInterface $at): float
    {
        $schedule = $this->scheduleForDate($at);

        if (! $schedule || ! $this->workdayService->isWorkday($at) || ! $this->isWithinWorkingWindow($at)) {
            return 0.0;
        }

        $workdaysInMonth = $this->workdayService->countWorkdaysInMonth($at);

        if ($workdaysInMonth === 0) {
            return 0.0;
        }

        return (float) $schedule->monthly_net_salary / ($workdaysInMonth * $this->workMinutesPerDay());
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

    private function eligibleMinutesForDay(CarbonInterface $day, CarbonInterface $periodStart, CarbonInterface $periodEnd): int
    {
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = Carbon::parse($day)->endOfDay();

        $start = $periodStart->greaterThan($dayStart) ? Carbon::parse($periodStart) : $dayStart;
        $end = $periodEnd->lessThan($dayEnd) ? Carbon::parse($periodEnd) : $dayEnd;

        if ($end->lte($start)) {
            return 0;
        }

        $eligibleMinutes = 0;

        foreach (self::WORK_WINDOWS as [$windowStartTime, $windowEndTime]) {
            $windowStart = Carbon::parse($day)->setTimeFromTimeString($windowStartTime);
            $windowEnd = Carbon::parse($day)->setTimeFromTimeString($windowEndTime);

            $segmentStart = $start->greaterThan($windowStart) ? $start : $windowStart;
            $segmentEnd = $end->lessThan($windowEnd) ? $end : $windowEnd;

            if ($segmentEnd->gt($segmentStart)) {
                $eligibleMinutes += (int) floor($segmentStart->diffInSeconds($segmentEnd) / 60);
            }
        }

        return $eligibleMinutes;
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

    private function workMinutesPerDay(): int
    {
        $minutes = 0;

        foreach (self::WORK_WINDOWS as [$windowStartTime, $windowEndTime]) {
            $windowStart = Carbon::createFromFormat('H:i', $windowStartTime);
            $windowEnd = Carbon::createFromFormat('H:i', $windowEndTime);
            $minutes += $windowStart->diffInMinutes($windowEnd);
        }

        return $minutes;
    }
}

