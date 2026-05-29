<?php

namespace Tests\Feature;

use App\Models\ProjectionScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_endpoints_run_save_load_and_compare(): void
    {
        $payload = $this->payload();

        $runResponse = $this->postJson(route('projection.run'), $payload);
        $runResponse->assertOk();
        $runResponse->assertJsonPath('months.0.month', '2026-06');

        $saveResponse = $this->postJson(route('projection.scenarios.save'), array_merge([
            'name' => 'Scenario A',
            'notes' => 'Base case',
        ], $payload));

        $saveResponse->assertOk();
        $scenarioAId = $saveResponse->json('scenario.id');

        $this->assertDatabaseHas('projection_scenarios', [
            'id' => $scenarioAId,
            'name' => 'Scenario A',
        ]);

        $this->assertDatabaseHas('projection_results_cache', [
            'scenario_id' => $scenarioAId,
        ]);

        $showResponse = $this->getJson(route('projection.scenarios.show', ['scenario' => $scenarioAId]));
        $showResponse->assertOk();
        $showResponse->assertJsonPath('scenario.name', 'Scenario A');

        $secondPayload = $payload;
        $secondPayload['scenario']['starting_coh'] = 1000;

        $scenarioB = ProjectionScenario::query()->create([
            'name' => 'Scenario B',
            'parameters_json' => $secondPayload,
            'notes' => 'Alternative',
        ]);

        $compareResponse = $this->postJson(route('projection.compare'), [
            'scenario_ids' => [$scenarioAId, $scenarioB->id],
        ]);

        $compareResponse->assertOk();
        $compareResponse->assertJsonCount(2, 'comparisons');
    }

    private function payload(): array
    {
        return [
            'scenario' => [
                'start_month' => '2026-06',
                'end_month' => '2026-08',
                'starting_coh' => 0,
                'starting_elr' => 0,
                'starting_epf' => 0,
            ],
            'employment' => [
                'probation_salary' => 1800,
                'confirmed_salary' => 2200,
                'probation_duration_months' => 3,
                'salary_start_month' => '2026-06',
                'salary_paid_in_arrears' => true,
            ],
            'cost_of_living' => [
                'bcol_amount' => 700,
                'fcol_lite_amount' => 900,
                'fcol_max_amount' => 1200,
                'fcol_lite_start_month' => '2026-07',
                'fcol_max_start_month' => '2026-08',
            ],
            'ptptn' => [
                'waiver_granted' => false,
                'monthly_repayment' => 120,
                'repayment_start_month' => '2026-08',
            ],
            'bnpl' => [
                [
                    'name' => 'Phone',
                    'monthly_amount' => 150,
                    'start_month' => '2026-06',
                    'end_month' => '2026-08',
                ],
            ],
            'events' => [
                [
                    'month' => '2026-08',
                    'type' => 'one_off_expense',
                    'amount' => 500,
                    'note' => 'Laptop repair',
                ],
            ],
            'elr' => [
                'schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => '2026-08',
                        'amount' => 50,
                    ],
                ],
            ],
            'epf' => [
                'employee_rate_percent' => 11.0,
                'employer_rate_percent' => 13.0,
            ],
        ];
    }
}
