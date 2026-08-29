<?php

namespace App\Services\Projection;

class SalaryContributionCalculator
{
    private const SOCSO_L24_EFFECTIVE_MONTH = '2026-06';

    public function __construct(
        private readonly EPFCalculator $epfCalculator,
        private readonly StatutoryDeductionResolver $statutoryDeductionResolver,
    ) {
    }

    public function calculate(float $grossSalary, string $month, array $contributions): array
    {
        $totals = [
            'employee_epf' => 0.0,
            'employer_epf' => 0.0,
            'socso' => 0.0,
            'socso_l24' => 0.0,
            'eis' => 0.0,
            'custom' => 0.0,
        ];
        $statutory = $this->statutoryDeductionResolver->resolve($grossSalary);

        foreach ($contributions as $contribution) {
            $type = (string) ($contribution['type'] ?? '');
            $amount = match ($type) {
                'employee_epf' => $this->epfCalculator->employeeContribution($grossSalary, [
                    'employee_rate_percent' => (float) ($contribution['rate_percent'] ?? 0),
                ]),
                'employer_epf' => $this->epfCalculator->employerContribution($grossSalary, [
                    'employer_rate_percent' => (float) ($contribution['rate_percent'] ?? 0),
                ]),
                'socso' => (float) ($statutory['socso'] ?? 0),
                'socso_l24' => $month >= self::SOCSO_L24_EFFECTIVE_MONTH
                    ? (float) ($statutory['socso_l24'] ?? 0)
                    : 0.0,
                'eis' => (float) ($statutory['eis'] ?? 0),
                'custom' => max(0, (float) ($contribution['amount'] ?? 0)),
                default => 0.0,
            };

            if (array_key_exists($type, $totals)) {
                $totals[$type] += $amount;
            }
        }

        $totals['deductions'] = $totals['employee_epf']
            + $totals['socso']
            + $totals['socso_l24']
            + $totals['eis']
            + $totals['custom'];

        return $totals;
    }
}
