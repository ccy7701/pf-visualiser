<?php

namespace App\Services\Projection;

class ELRCalculator
{
    public function allocationForMonth(string $month, array $elr, array $events = []): float
    {
        $scheduleAmount = $this->allocationFromSchedules($month, $elr['schedules'] ?? []);

        if ($scheduleAmount !== null) {
            $amount = $scheduleAmount;
        } else {
            $daily = (float) ($elr['daily_contribution'] ?? 0);
            $monthly = (float) ($elr['monthly_contribution'] ?? 0);
            $amount = $monthly + ($daily * 30);
        }

        foreach ($events as $event) {
            if (($event['type'] ?? null) === 'elr_override') {
                $amount = (float) ($event['amount'] ?? 0);
            }
        }

        return max(0.0, $amount);
    }

    private function allocationFromSchedules(string $month, array $schedules): ?float
    {
        foreach ($schedules as $schedule) {
            $start = $schedule['start_month'] ?? null;
            $end = $schedule['end_month'] ?? null;

            if (! $start || ! $end) {
                continue;
            }

            $monthIndex = MonthHelper::toIndex($month);
            $startIndex = MonthHelper::toIndex((string) $start);
            $endIndex = MonthHelper::toIndex((string) $end);

            if ($monthIndex >= $startIndex && $monthIndex <= $endIndex) {
                return (float) ($schedule['amount'] ?? 0);
            }
        }

        return null;
    }
}
