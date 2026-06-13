<?php

namespace App\Services\Counter;

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

    public function computeAccruedSalary(CarbonInterface $asOf, array $realizedSalaryByMonth = []): array
    {
        $firstSchedule = SalarySchedule::query()->orderBy('effective_from')->first();

        if (! $firstSchedule) {
            return [
                'accrued_salary' => 0.0,
                'current_month_accrued_salary' => 0.0,
                'scheduled_accrued_salary' => 0.0,
                'realized_salary' => 0.0,
                'increment_per_second' => 0.0,
            ];
        }

        $accrualStart = Carbon::parse($firstSchedule->effective_from)->startOfDay();

        if ($asOf->lte($accrualStart)) {
            return [
                'accrued_salary' => 0.0,
                'current_month_accrued_salary' => 0.0,
                'scheduled_accrued_salary' => 0.0,
                'realized_salary' => 0.0,
                'increment_per_second' => 0.0,
            ];
        }

        $totalAccrued = 0.0;
        $accruedByMonth = [];
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
                    $dayAccrued = $dailyAccruedSalary * ($eligibleSeconds / self::WORK_SECONDS_PER_DAY);
                    $month = $cursor->format('Y-m');
                    $accruedByMonth[$month] = ($accruedByMonth[$month] ?? 0.0) + $dayAccrued;
                    $totalAccrued += $dayAccrued;
                }
            }

            $cursor->addDay()->startOfDay();
        }

        $realizedTotal = 0.0;
        $unpaidAccrued = 0.0;

        foreach ($accruedByMonth as $month => $scheduledAccrued) {
            $realizedForMonth = max(0.0, (float) ($realizedSalaryByMonth[$month] ?? 0));
            $realizedTotal += min($scheduledAccrued, $realizedForMonth);
            $unpaidAccrued += max(0.0, $scheduledAccrued - $realizedForMonth);
        }

        $currentMonth = $asOf->format('Y-m');
        $currentMonthUnpaid = max(
            0.0,
            (float) ($accruedByMonth[$currentMonth] ?? 0.0) - max(0.0, (float) ($realizedSalaryByMonth[$currentMonth] ?? 0))
        );

        return [
            'accrued_salary' => $unpaidAccrued,
            'current_month_accrued_salary' => $currentMonthUnpaid,
            'scheduled_accrued_salary' => $totalAccrued,
            'realized_salary' => $realizedTotal,
            'increment_per_second' => $currentMonthUnpaid > 0 || empty($realizedSalaryByMonth[$currentMonth])
                ? $this->currentIncrementPerSecond($asOf)
                : 0.0,
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
