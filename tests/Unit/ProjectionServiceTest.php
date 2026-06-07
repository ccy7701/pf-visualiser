<?php

namespace Tests\Unit;

use App\Services\ProjectionService;
use App\Services\Projection\SalaryCalculator;
use Tests\TestCase;

class ProjectionServiceTest extends TestCase
{
    public function test_salary_calculator_uses_future_salary_schedule_increments(): void
    {
        $calculator = app(SalaryCalculator::class);
        $employment = [
            'salary_schedules' => [
                [
                    'start_month' => '2026-06',
                    'end_month' => '2026-08',
                    'monthly_gross_salary' => 1800,
                    'note' => 'Initial salary',
                ],
                [
                    'start_month' => '2026-09',
                    'end_month' => null,
                    'monthly_gross_salary' => 2400,
                    'note' => 'Raise',
                ],
            ],
            'salary_paid_in_arrears' => false,
        ];

        $this->assertSame(1800.0, $calculator->grossForMonth('2026-08', $employment));
        $this->assertSame(2400.0, $calculator->grossForMonth('2026-09', $employment));
    }

    public function test_projection_uses_schedule_specific_epf_rates(): void
    {
        $service = app(ProjectionService::class);

        $payload = [
            'scenario' => [
                'start_month' => '2026-06',
                'end_month' => '2026-06',
                'starting_coh' => 0,
                'starting_elr' => 0,
                'starting_epf' => 0,
            ],
            'employment' => [
                'salary_schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => null,
                        'monthly_gross_salary' => 1000,
                        'employee_epf_rate_percent' => 5,
                        'employer_epf_rate_percent' => 7,
                        'note' => 'Custom EPF',
                    ],
                ],
                'salary_paid_in_arrears' => false,
            ],
            'cost_of_living' => [
                'budgets' => [
                    'bcol' => ['category_allocations' => []],
                    'fcol_lite' => ['category_allocations' => []],
                    'fcol_max' => ['category_allocations' => []],
                ],
                'monthly_budget_selection' => [],
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 0,
                'repayment_start_month' => null,
            ],
            'bnpl' => [],
            'events' => [],
            'elr' => [
                'schedules' => [],
                'note' => '',
                'compound_interest_enabled' => false,
                'annual_interest_rate_percent' => 0,
            ],
            'epf' => [
                'employee_rate_percent' => 10,
                'employer_rate_percent' => 20,
            ],
        ];

        $month = $service->project($payload)['months'][0];

        $this->assertSame(50.0, $month['employee_epf']);
        $this->assertSame(70.0, $month['employer_epf']);
        $this->assertSame(120.0, $month['closing_epf']);
    }

    public function test_projection_respects_locked_rules(): void
    {
        $service = app(ProjectionService::class);

        $payload = [
            'scenario' => [
                'start_month' => '2026-06',
                'end_month' => '2026-08',
                'starting_coh' => 0,
                'starting_elr' => 0,
                'starting_epf' => 0,
            ],
            'employment' => [
                'salary_schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => '2026-06',
                        'monthly_gross_salary' => 1000,
                        'note' => 'Probation',
                    ],
                    [
                        'start_month' => '2026-07',
                        'end_month' => '2026-07',
                        'monthly_gross_salary' => 2000,
                        'note' => 'Confirmed',
                    ],
                    [
                        'start_month' => '2026-08',
                        'end_month' => null,
                        'monthly_gross_salary' => 2500,
                        'note' => 'Raise',
                    ],
                ],
                'salary_paid_in_arrears' => true,
            ],
            'cost_of_living' => [
                'budgets' => [
                    'bcol' => [
                        'category_allocations' => [
                            ['category_id' => 1, 'name' => 'Food', 'amount' => 60],
                            ['category_id' => 2, 'name' => 'Transport', 'amount' => 40],
                        ],
                    ],
                    'fcol_lite' => [
                        'category_allocations' => [
                            ['category_id' => 1, 'name' => 'Food', 'amount' => 90],
                            ['category_id' => 2, 'name' => 'Transport', 'amount' => 60],
                        ],
                    ],
                    'fcol_max' => [
                        'category_allocations' => [
                            ['category_id' => 1, 'name' => 'Food', 'amount' => 180],
                            ['category_id' => 2, 'name' => 'Transport', 'amount' => 120],
                        ],
                    ],
                ],
                'monthly_budget_selection' => [
                    ['month' => '2026-07', 'budget' => 'fcol_lite'],
                    ['month' => '2026-08', 'budget' => 'fcol_max'],
                ],
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 50,
                'repayment_start_month' => '2026-07',
            ],
            'bnpl' => [
                [
                    'month' => '2026-06',
                    'amount' => 20,
                    'note' => 'Phone',
                ],
                [
                    'month' => '2026-07',
                    'amount' => 20,
                    'note' => 'Phone',
                ],
                [
                    'month' => '2026-08',
                    'amount' => 20,
                    'note' => 'Phone',
                ],
            ],
            'events' => [],
            'elr' => [
                'schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => '2026-08',
                        'amount' => 10,
                    ],
                ],
                'note' => '',
                'compound_interest_enabled' => false,
                'annual_interest_rate_percent' => 0,
            ],
            'epf' => [
                'employee_rate_percent' => 10.0,
                'employer_rate_percent' => 20.0,
            ],
        ];

        $result = $service->project($payload);
        $months = $result['months'];

        $this->assertCount(3, $months);

        // Arrears full-month lag: June has no salary, July pays June probation salary.
        $this->assertSame(0.0, $months[0]['gross_income']);
        $this->assertSame(1000.0, $months[1]['gross_income']);
        $this->assertSame(2000.0, $months[2]['gross_income']);

        // Month-specific budget selection: BCOL -> FCOL Lite -> FCOL Max.
        $this->assertSame(100.0, $months[0]['living_expenses']);
        $this->assertSame(150.0, $months[1]['living_expenses']);
        $this->assertSame(300.0, $months[2]['living_expenses']);

        // COH can go negative. ELR schedule amount is interpreted as daily contribution.
        $this->assertSame(-420.0, $months[0]['closing_coh']);

        // EPF from gross salary only, fixed rates.
        $this->assertSame(300.0, $months[1]['closing_epf']);
        $this->assertSame(900.0, $months[2]['closing_epf']);
    }

    public function test_projection_uses_custom_budget_profiles(): void
    {
        $service = app(ProjectionService::class);

        $payload = [
            'scenario' => [
                'start_month' => '2026-06',
                'end_month' => '2026-07',
                'starting_coh' => 0,
                'starting_elr' => 0,
                'starting_epf' => 0,
            ],
            'employment' => [
                'salary_schedules' => [[
                    'start_month' => '2026-06',
                    'end_month' => null,
                    'monthly_gross_salary' => 0,
                    'note' => '',
                ]],
                'salary_paid_in_arrears' => false,
            ],
            'cost_of_living' => [
                'budgets' => [
                    'lean_month' => [
                        'name' => 'Lean Month',
                        'category_allocations' => [
                            ['category_id' => 'food', 'name' => 'Food', 'amount' => 75],
                        ],
                    ],
                    'travel_month' => [
                        'name' => 'Travel Month',
                        'category_allocations' => [
                            ['category_id' => 'food', 'name' => 'Food', 'amount' => 120],
                            ['category_id' => 'transportation', 'name' => 'Transportation', 'amount' => 430],
                        ],
                    ],
                ],
                'monthly_budget_selection' => [
                    ['month' => '2026-07', 'budget' => 'travel_month'],
                ],
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 0,
                'repayment_start_month' => null,
            ],
            'bnpl' => [],
            'events' => [],
            'elr' => [
                'schedules' => [],
                'note' => '',
                'compound_interest_enabled' => false,
                'annual_interest_rate_percent' => 0,
            ],
            'epf' => [
                'employee_rate_percent' => 0,
                'employer_rate_percent' => 0,
            ],
        ];

        $months = $service->project($payload)['months'];

        $this->assertSame(75.0, $months[0]['living_expenses']);
        $this->assertSame(550.0, $months[1]['living_expenses']);
    }

    public function test_elr_compound_interest_applies_interest_before_daily_contribution(): void
    {
        $service = app(ProjectionService::class);

        $payload = [
            'scenario' => [
                'start_month' => '2026-06',
                'end_month' => '2026-06',
                'starting_coh' => 0,
                'starting_elr' => 100,
                'starting_epf' => 0,
            ],
            'employment' => [
                'salary_schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => null,
                        'monthly_gross_salary' => 0,
                        'note' => '',
                    ],
                ],
                'salary_paid_in_arrears' => true,
            ],
            'cost_of_living' => [
                'budgets' => [
                    'bcol' => ['category_allocations' => []],
                    'fcol_lite' => ['category_allocations' => []],
                    'fcol_max' => ['category_allocations' => []],
                ],
                'monthly_budget_selection' => [],
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 0,
                'repayment_start_month' => null,
            ],
            'bnpl' => [],
            'events' => [],
            'elr' => [
                'schedules' => [[
                    'start_month' => '2026-06',
                    'end_month' => '2026-06',
                    'amount' => 5,
                ]],
                'note' => 'compound case',
                'compound_interest_enabled' => true,
                'annual_interest_rate_percent' => 3.65,
            ],
            'epf' => [
                'employee_rate_percent' => 0,
                'employer_rate_percent' => 0,
            ],
        ];

        $result = $service->project($payload);
        $month = $result['months'][0];

        // June has 30 days: contribution should remain daily_amount * days.
        $this->assertSame(150.0, $month['elr_contribution']);
        // Interest should be positive and included in closing ELR.
        $this->assertGreaterThan(0.0, $month['elr_interest']);
        $this->assertGreaterThan(250.0, $month['closing_elr']);
    }
}
