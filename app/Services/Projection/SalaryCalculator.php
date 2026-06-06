<?php

namespace App\Services\Projection;

class SalaryCalculator
{
    public function grossForMonth(string $month, array $employment): float
    {
        return (float) ($this->scheduleForMonth($month, $employment)['monthly_gross_salary'] ?? 0);
    }

    public function scheduleForMonth(string $month, array $employment): ?array
    {
        $payMonthIndex = MonthHelper::toIndex($month);
        $workMonthIndex = ! empty($employment['salary_paid_in_arrears'])
            ? $payMonthIndex - 1
            : $payMonthIndex;

        foreach ($employment['salary_schedules'] ?? [] as $schedule) {
            $startIndex = MonthHelper::toIndex((string) $schedule['start_month']);
            $endMonth = $schedule['end_month'] ?? null;
            $endIndex = $endMonth ? MonthHelper::toIndex((string) $endMonth) : PHP_INT_MAX;

            if ($workMonthIndex >= $startIndex && $workMonthIndex <= $endIndex) {
                return $schedule;
            }
        }

        return null;
    }
}
