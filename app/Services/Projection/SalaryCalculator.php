<?php

namespace App\Services\Projection;

class SalaryCalculator
{
    public function grossForMonth(string $month, array $employment): float
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
                return (float) $schedule['monthly_gross_salary'];
            }
        }

        return 0.0;
    }
}
