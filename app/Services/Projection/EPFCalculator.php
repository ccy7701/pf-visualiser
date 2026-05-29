<?php

namespace App\Services\Projection;

class EPFCalculator
{
    public function employeeContribution(float $grossSalary, array $epf): float
    {
        return $grossSalary * $this->percentToRatio($epf['employee_rate_percent'] ?? 0);
    }

    public function employerContribution(float $grossSalary, array $epf): float
    {
        return $grossSalary * $this->percentToRatio($epf['employer_rate_percent'] ?? 0);
    }

    private function percentToRatio(float|int|string $percent): float
    {
        return ((float) $percent) / 100;
    }
}
