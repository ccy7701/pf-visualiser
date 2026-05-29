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
                'bcol_amount' => 100,
                'fcol_lite_amount' => 150,
                'fcol_max_amount' => 300,
                'fcol_lite_start_month' => '2026-07',
                'fcol_max_start_month' => '2026-08',
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 50,
                'repayment_start_month' => '2026-07',
            ],
            'bnpl' => [
                [
                    'name' => 'Phone',
                    'monthly_amount' => 20,
                    'start_month' => '2026-06',
                    'end_month' => '2026-08',
                ],
            ],
            'events' => [],
            'elr' => [
                'daily_contribution' => 0,
                'monthly_contribution' => 10,
                'schedules' => [],
            ],
            'epf' => [
                'employee_rate' => 0.10,
                'employer_rate' => 0.20,
            ],
        ];

        $result = $service->project($payload);
        $months = $result['months'];

        $this->assertCount(3, $months);

        // Arrears full-month lag: June has no salary, July pays June probation salary.
        $this->assertSame(0.0, $months[0]['gross_income']);
        $this->assertSame(1000.0, $months[1]['gross_income']);
        $this->assertSame(2000.0, $months[2]['gross_income']);

        // COL tier override precedence: BCOL -> FCOL Lite -> FCOL Max.
        $this->assertSame(100.0, $months[0]['living_expenses']);
        $this->assertSame(150.0, $months[1]['living_expenses']);
        $this->assertSame(300.0, $months[2]['living_expenses']);

        // COH can go negative.
        $this->assertSame(-130.0, $months[0]['closing_coh']);

        // EPF from gross salary only, fixed rates.
        $this->assertSame(300.0, $months[1]['closing_epf']);
        $this->assertSame(900.0, $months[2]['closing_epf']);
    }
}
