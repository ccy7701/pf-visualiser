<?php

namespace Tests\Unit;

use App\Services\ProjectionService;
use Tests\TestCase;

class ProjectionServiceTest extends TestCase
{
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
                'probation_salary' => 1000,
                'confirmed_salary' => 2000,
                'probation_duration_months' => 1,
                'salary_start_month' => '2026-06',
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
}
