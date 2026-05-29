<?php

namespace App\Services\Projection;

class EPFCalculator
{
    public function employeeContribution(float $grossSalary, array $epf): float
    {
        return $grossSalary * (float) ($epf['employee_rate'] ?? 0);
    }

    public function employerContribution(float $grossSalary, array $epf): float
    {
        return $grossSalary * (float) ($epf['employer_rate'] ?? 0);
    }
}
