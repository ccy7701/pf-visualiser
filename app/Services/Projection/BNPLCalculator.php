<?php

namespace App\Services\Projection;

class BNPLCalculator
{
    public function repaymentForMonth(string $month, array $bnplSchedules): float
    {
        $total = 0.0;

        foreach ($bnplSchedules as $schedule) {
            $startMonth = $schedule['start_month'] ?? null;
            $endMonth = $schedule['end_month'] ?? null;

            if (! $startMonth || ! $endMonth) {
                continue;
            }

            $inRange = MonthHelper::toIndex($month) >= MonthHelper::toIndex((string) $startMonth)
                && MonthHelper::toIndex($month) <= MonthHelper::toIndex((string) $endMonth);

            if ($inRange) {
                $total += (float) ($schedule['monthly_amount'] ?? 0);
            }
        }

        return $total;
    }
}
