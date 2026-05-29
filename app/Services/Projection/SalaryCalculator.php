<?php

namespace App\Services\Projection;

class SalaryCalculator
{
    public function grossForMonth(string $month, array $employment): float
    {
        $payMonthIndex = MonthHelper::toIndex($month);
        $salaryStartIndex = MonthHelper::toIndex((string) $employment['salary_start_month']);

        $workMonthIndex = ! empty($employment['salary_paid_in_arrears'])
            ? $payMonthIndex - 1
            : $payMonthIndex;

        if ($workMonthIndex < $salaryStartIndex) {
            return 0.0;
        }

        $monthsSinceStart = $workMonthIndex - $salaryStartIndex;
        $probationDuration = max(0, (int) ($employment['probation_duration_months'] ?? 0));

        if ($monthsSinceStart < $probationDuration) {
            return (float) $employment['probation_salary'];
        }

        return (float) $employment['confirmed_salary'];
    }
}
