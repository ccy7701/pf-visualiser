<?php

namespace App\Services;

use App\Services\Projection\BNPLCalculator;
use App\Services\Projection\ELRCalculator;
use App\Services\Projection\EPFCalculator;
use App\Services\Projection\ExpenseCalculator;
use App\Services\Projection\MonthHelper;
use App\Services\Projection\PTPTNCalculator;
use App\Services\Projection\ProjectionResultBuilder;
use App\Services\Projection\SalaryCalculator;
use App\Services\Projection\StatutoryDeductionResolver;

class ProjectionService
{
    private const SOCSO_L24_EFFECTIVE_MONTH = '2026-06';

    public function __construct(
        private readonly SalaryCalculator $salaryCalculator,
        private readonly ExpenseCalculator $expenseCalculator,
        private readonly BNPLCalculator $bnplCalculator,
        private readonly PTPTNCalculator $ptptnCalculator,
        private readonly ELRCalculator $elrCalculator,
        private readonly EPFCalculator $epfCalculator,
        private readonly StatutoryDeductionResolver $statutoryDeductionResolver,
        private readonly ProjectionResultBuilder $projectionResultBuilder,
    ) {
    }

    public function project(array $payload): array
    {
        $normalized = $this->normalizePayload($payload);

        $scenario = $normalized['scenario'];
        $employment = $normalized['employment'];
        $costOfLiving = $normalized['cost_of_living'];
        $ptptn = $normalized['ptptn'];
        $bnpl = $normalized['bnpl'];
        $events = $normalized['events'];
        $elr = $normalized['elr'];
        $epf = $normalized['epf'];

        $months = MonthHelper::sequence($scenario['start_month'], $scenario['end_month']);

        $openingCoh = (float) $scenario['starting_coh'];
        $openingElr = (float) $scenario['starting_elr'];
        $openingEpf = (float) $scenario['starting_epf'];

        $rows = [];

        foreach ($months as $month) {
            $monthEvents = $this->eventsForMonth($events, $month);

            $salarySchedule = $this->salaryCalculator->scheduleForMonth($month, $employment);
            $grossIncome = (float) ($salarySchedule['monthly_gross_salary'] ?? 0);
            $monthEpf = $this->epfForSalarySchedule($epf, $salarySchedule);
            $employeeEpf = $this->epfCalculator->employeeContribution($grossIncome, $monthEpf);
            $employerEpf = $this->epfCalculator->employerContribution($grossIncome, $monthEpf);
            $statutory = $this->statutoryDeductionResolver->resolve($grossIncome);
            $socso = (float) ($statutory['socso'] ?? 0);
            $socsoL24 = $employment['socso_l24_enabled'] && $month >= self::SOCSO_L24_EFFECTIVE_MONTH
                ? (float) ($statutory['socso_l24'] ?? 0)
                : 0.0;
            $eis = (float) ($statutory['eis'] ?? 0);
            $netIncome = $grossIncome - $employeeEpf - $socso - $socsoL24 - $eis;

            $allowances = $this->sumEventsByType($monthEvents, 'allowance');
            $household = $this->sumEventsByType($monthEvents, 'household');
            $oneOffIncome = $this->sumEventsByType($monthEvents, 'one_off_income');
            $oneOffExpense = $this->sumEventsByType($monthEvents, 'one_off_expense');
            $netIncomeDisplay = $netIncome + $allowances + $oneOffIncome;

            $livingExpenses = $this->expenseCalculator->livingCostForMonth($month, $costOfLiving) + $household;
            $bnplRepayment = $this->bnplCalculator->repaymentForMonth($month, $bnpl);
            $ptptnRepayment = $this->ptptnCalculator->repaymentForMonth($month, $ptptn);
            $elrMonthProjection = $this->elrCalculator->projectMonthBalance($month, $openingElr, $elr, $monthEvents);
            $elrContribution = (float) ($elrMonthProjection['contribution'] ?? 0);

            $closingCoh = $openingCoh
                + $netIncome
                + $allowances
                + $oneOffIncome
                - $livingExpenses
                - $bnplRepayment
                - $ptptnRepayment
                - $oneOffExpense
                - $elrContribution;

            $closingElr = (float) ($elrMonthProjection['closing_elr'] ?? ($openingElr + $elrContribution));
            $closingEpf = $openingEpf + $employeeEpf + $employerEpf;

            $rows[] = [
                'month' => $month,
                'opening_coh' => round($openingCoh, 2),
                'closing_coh' => round($closingCoh, 2),
                'opening_elr' => round($openingElr, 2),
                'closing_elr' => round($closingElr, 2),
                'opening_epf' => round($openingEpf, 2),
                'closing_epf' => round($closingEpf, 2),
                'gross_income' => round($grossIncome, 2),
                'net_income' => round($netIncomeDisplay, 2),
                'allowances' => round($allowances, 2),
                'one_off_income' => round($oneOffIncome, 2),
                'expenses' => round($livingExpenses + $oneOffExpense, 2),
                'living_expenses' => round($livingExpenses, 2),
                'one_off_expenses' => round($oneOffExpense, 2),
                'bnpl' => round($bnplRepayment, 2),
                'ptptn' => round($ptptnRepayment, 2),
                'debt_servicing' => round($bnplRepayment + $ptptnRepayment, 2),
                'elr_contribution' => round($elrContribution, 2),
                'elr_interest' => round((float) ($elrMonthProjection['interest'] ?? 0), 2),
                'employee_epf' => round($employeeEpf, 2),
                'employer_epf' => round($employerEpf, 2),
                'socso' => round($socso, 2),
                'socso_l24' => round($socsoL24, 2),
                'eis' => round($eis, 2),
            ];

            $openingCoh = $closingCoh;
            $openingElr = $closingElr;
            $openingEpf = $closingEpf;
        }

        $meta = [
            'start_month' => $scenario['start_month'],
            'end_month' => $scenario['end_month'],
            'months_count' => count($months),
            'salary_paid_in_arrears' => (bool) $employment['salary_paid_in_arrears'],
            'socso_l24_enabled' => (bool) $employment['socso_l24_enabled'],
            'ptptn_waiver_granted' => (bool) $ptptn['waiver_granted'],
        ];

        return $this->projectionResultBuilder->build($meta, $rows);
    }

    public function normalizePayload(array $payload): array
    {
        $scenario = $payload['scenario'] ?? [];
        $employment = $payload['employment'] ?? [];
        $costOfLiving = $payload['cost_of_living'] ?? [];
        $ptptn = $payload['ptptn'] ?? [];
        $bnpl = $payload['bnpl'] ?? [];
        $events = $payload['events'] ?? [];
        $elr = $payload['elr'] ?? [];
        $epf = $payload['epf'] ?? [];

        return [
            'scenario' => [
                'start_month' => MonthHelper::normalize((string) $scenario['start_month']),
                'end_month' => MonthHelper::normalize((string) $scenario['end_month']),
                'starting_coh' => (float) ($scenario['starting_coh'] ?? 0),
                'starting_elr' => (float) ($scenario['starting_elr'] ?? 0),
                'starting_epf' => (float) ($scenario['starting_epf'] ?? 0),
            ],
            'employment' => [
                'salary_schedules' => $this->normalizeSalarySchedules($employment),
                'salary_paid_in_arrears' => filter_var($employment['salary_paid_in_arrears'] ?? false, FILTER_VALIDATE_BOOL),
                'socso_l24_enabled' => filter_var($employment['socso_l24_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            ],
            'cost_of_living' => [
                'budgets' => $this->normalizeCostOfLivingBudgets($costOfLiving),
                'monthly_budget_selection' => array_values(array_map(function (array $selection) {
                    return [
                        'month' => MonthHelper::normalize((string) $selection['month']),
                        'budget' => (string) ($selection['budget'] ?? 'bcol'),
                    ];
                }, array_filter($costOfLiving['monthly_budget_selection'] ?? [], fn ($selection) => is_array($selection)))),
            ],
            'ptptn' => [
                'waiver_granted' => filter_var($ptptn['waiver_granted'] ?? false, FILTER_VALIDATE_BOOL),
                'monthly_repayment' => (float) ($ptptn['monthly_repayment'] ?? 0),
                'repayment_start_month' => $this->normalizeOptionalMonth($ptptn['repayment_start_month'] ?? null),
                'interim_payment_months' => filter_var($ptptn['waiver_granted'] ?? false, FILTER_VALIDATE_BOOL)
                    ? max(1, (int) ($ptptn['interim_payment_months'] ?? 1))
                    : null,
            ],
            'bnpl' => array_values(array_map(function (array $item) {
                return [
                    'month' => MonthHelper::normalize((string) $item['month']),
                    'amount' => (float) ($item['amount'] ?? 0),
                    'note' => (string) ($item['note'] ?? ''),
                ];
            }, array_filter($bnpl, fn ($item) => is_array($item)))),
            'events' => array_values(array_map(function (array $item) {
                return [
                    'month' => MonthHelper::normalize((string) $item['month']),
                    'type' => (string) ($item['type'] ?? ''),
                    'amount' => (float) ($item['amount'] ?? 0),
                    'note' => (string) ($item['note'] ?? ''),
                ];
            }, array_filter($events, fn ($item) => is_array($item)))),
            'elr' => [
                'schedules' => array_values(array_map(function (array $schedule) {
                    return [
                        'start_month' => MonthHelper::normalize((string) $schedule['start_month']),
                        'end_month' => MonthHelper::normalize((string) $schedule['end_month']),
                        'amount' => (float) ($schedule['amount'] ?? 0),
                    ];
                }, array_filter($elr['schedules'] ?? [], fn ($schedule) => is_array($schedule)))),
                'note' => (string) ($elr['note'] ?? ''),
                'compound_interest_enabled' => filter_var($elr['compound_interest_enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'annual_interest_rate_percent' => (float) ($elr['annual_interest_rate_percent'] ?? 0),
            ],
            'epf' => [
                'employee_rate_percent' => (float) ($epf['employee_rate_percent'] ?? 0),
                'employer_rate_percent' => (float) ($epf['employer_rate_percent'] ?? 0),
            ],
        ];
    }

    private function normalizeOptionalMonth(?string $month): ?string
    {
        if ($month === null || trim($month) === '') {
            return null;
        }

        return MonthHelper::normalize($month);
    }

    private function normalizeSalarySchedules(array $employment): array
    {
        $rawSchedules = array_filter($employment['salary_schedules'] ?? [], fn ($schedule) => is_array($schedule));

        if ($rawSchedules === [] && array_key_exists('salary_start_month', $employment)) {
            $rawSchedules = $this->legacySalarySchedules($employment);
        }

        $schedules = array_values(array_map(function (array $schedule): array {
            return [
                'start_month' => MonthHelper::normalize((string) $schedule['start_month']),
                'end_month' => $this->normalizeOptionalMonth($schedule['end_month'] ?? null),
                'monthly_gross_salary' => (float) ($schedule['monthly_gross_salary'] ?? 0),
                'employee_epf_rate_percent' => $this->normalizeOptionalPercent($schedule['employee_epf_rate_percent'] ?? null),
                'employer_epf_rate_percent' => $this->normalizeOptionalPercent($schedule['employer_epf_rate_percent'] ?? null),
                'note' => (string) ($schedule['note'] ?? ''),
            ];
        }, $rawSchedules));

        usort($schedules, fn (array $a, array $b) => MonthHelper::toIndex($a['start_month']) <=> MonthHelper::toIndex($b['start_month']));

        return $schedules;
    }

    private function legacySalarySchedules(array $employment): array
    {
        $salaryStartMonth = MonthHelper::normalize((string) $employment['salary_start_month']);
        $probationDuration = max(0, (int) ($employment['probation_duration_months'] ?? 0));
        $confirmedStartMonth = MonthHelper::fromIndex(MonthHelper::toIndex($salaryStartMonth) + $probationDuration);
        $schedules = [];

        if ($probationDuration > 0) {
            $schedules[] = [
                'start_month' => $salaryStartMonth,
                'end_month' => MonthHelper::fromIndex(MonthHelper::toIndex($confirmedStartMonth) - 1),
                'monthly_gross_salary' => (float) ($employment['probation_salary'] ?? 0),
                'employee_epf_rate_percent' => null,
                'employer_epf_rate_percent' => null,
                'note' => 'Probation',
            ];
        }

        $schedules[] = [
            'start_month' => $confirmedStartMonth,
            'end_month' => null,
            'monthly_gross_salary' => (float) ($employment['confirmed_salary'] ?? 0),
            'employee_epf_rate_percent' => null,
            'employer_epf_rate_percent' => null,
            'note' => 'Confirmed',
        ];

        return $schedules;
    }

    private function epfForSalarySchedule(array $epf, ?array $salarySchedule): array
    {
        if ($salarySchedule === null) {
            return $epf;
        }

        return [
            'employee_rate_percent' => $salarySchedule['employee_epf_rate_percent'] ?? $epf['employee_rate_percent'],
            'employer_rate_percent' => $salarySchedule['employer_epf_rate_percent'] ?? $epf['employer_rate_percent'],
        ];
    }

    private function normalizeOptionalPercent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeCostOfLivingBudgets(array $costOfLiving): array
    {
        $budgetNames = [
            'bcol' => 'BCOL',
            'fcol_lite' => 'FCOL Lite',
            'fcol_max' => 'FCOL Max',
        ];
        $normalized = [];

        foreach (($costOfLiving['budgets'] ?? []) as $key => $budget) {
            if (! is_array($budget)) {
                continue;
            }

            $profileKey = trim((string) $key);
            if ($profileKey === '') {
                continue;
            }

            $allocations = array_values(array_map(function (array $item): array {
                return [
                    'category_id' => (string) ($item['category_id'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'amount' => (float) ($item['amount'] ?? 0),
                ];
            }, array_filter($budget['category_allocations'] ?? [], fn ($item) => is_array($item))));

            $normalized[$profileKey] = [
                'name' => (string) ($budget['name'] ?? $budgetNames[$profileKey] ?? $profileKey),
                'category_allocations' => $allocations,
            ];
        }

        if ($normalized === []) {
            $normalized['bcol'] = [
                'name' => 'BCOL',
                'category_allocations' => [],
            ];
        }

        return $normalized;
    }

    private function eventsForMonth(array $events, string $month): array
    {
        return array_values(array_filter($events, fn (array $event) => ($event['month'] ?? null) === $month));
    }

    private function sumEventsByType(array $events, string $type): float
    {
        return array_reduce($events, function (float $carry, array $event) use ($type): float {
            if (($event['type'] ?? null) !== $type) {
                return $carry;
            }

            return $carry + (float) ($event['amount'] ?? 0);
        }, 0.0);
    }
}
