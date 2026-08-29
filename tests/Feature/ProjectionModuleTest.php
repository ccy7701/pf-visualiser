<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProjectionScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_endpoints_run_save_load_and_compare(): void
    {
        $expenseCategories = Category::query()->insertGetId([
            'name' => 'Food',
            'type' => 'expense',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transportCategory = Category::query()->insertGetId([
            'name' => 'Transportation',
            'type' => 'expense',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('projection.index'))
            ->assertOk()
            ->assertSee('Scenario Comparison')
            ->assertDontSee('Shared-range final TFP')
            ->assertDontSee('<th>Advantage</th>', false);

        $payload = $this->payload($expenseCategories, $transportCategory);

        $runResponse = $this->postJson(route('projection.run'), $payload);
        $runResponse->assertOk();
        $runResponse->assertJsonPath('months.0.month', '2026-06');
        $runResponse->assertJsonPath(
            'summary.final_tfp',
            $runResponse->json('summary.final_coh') + $runResponse->json('summary.final_elr') + $runResponse->json('summary.final_epf')
        );

        $waivedPayload = $payload;
        $waivedPayload['ptptn'] = [
            'waiver_granted' => true,
            'monthly_repayment' => 120,
            'repayment_start_month' => '2026-06',
            'interim_payment_months' => 2,
        ];
        $this->postJson(route('projection.run'), $waivedPayload)
            ->assertOk()
            ->assertJsonPath('months.0.ptptn', 120)
            ->assertJsonPath('months.1.ptptn', 120)
            ->assertJsonPath('months.2.ptptn', 0);

        unset($waivedPayload['ptptn']['interim_payment_months']);
        $this->postJson(route('projection.run'), $waivedPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ptptn.interim_payment_months');

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

        $updatedPayload = $payload;
        $updatedPayload['scenario']['starting_coh'] = 321;
        $updateResponse = $this->postJson(route('projection.scenarios.save'), array_merge([
            'scenario_id' => $scenarioAId,
            'name' => 'Scenario A',
            'notes' => 'Base case revised',
        ], $updatedPayload));
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('message', 'Scenario updated successfully.');
        $this->assertSame($scenarioAId, $updateResponse->json('scenario.id'));
        $this->assertDatabaseCount('projection_scenarios', 1);

        $secondPayload = $payload;
        $secondPayload['scenario']['starting_coh'] = 1000;
        $secondPayload['scenario']['end_month'] = '2026-09';

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
        $compareResponse->assertJsonCount(3, 'comparisons.0.result.months');
        $compareResponse->assertJsonCount(4, 'comparisons.1.result.months');
        $compareResponse->assertJsonPath('comparisons.0.result.months.0.month', '2026-06');
        $compareResponse->assertJsonPath('comparisons.1.result.months.3.month', '2026-09');
    }

    private function payload(int $foodCategoryId, int $transportCategoryId): array
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
                'salary_schedules' => [
                    [
                        'start_month' => '2026-06',
                        'end_month' => '2026-08',
                        'monthly_gross_salary' => 1800,
                        'note' => 'Probation',
                    ],
                    [
                        'start_month' => '2026-09',
                        'end_month' => null,
                        'monthly_gross_salary' => 2200,
                        'note' => 'Confirmed',
                    ],
                ],
                'salary_paid_in_arrears' => true,
                'socso_l24_enabled' => false,
            ],
            'cost_of_living' => [
                'budgets' => [
                    'bcol' => [
                        'category_allocations' => [
                            ['category_id' => $foodCategoryId, 'name' => 'Food', 'amount' => 500],
                            ['category_id' => $transportCategoryId, 'name' => 'Transportation', 'amount' => 200],
                        ],
                    ],
                    'fcol_lite' => [
                        'category_allocations' => [
                            ['category_id' => $foodCategoryId, 'name' => 'Food', 'amount' => 650],
                            ['category_id' => $transportCategoryId, 'name' => 'Transportation', 'amount' => 250],
                        ],
                    ],
                    'fcol_max' => [
                        'category_allocations' => [
                            ['category_id' => $foodCategoryId, 'name' => 'Food', 'amount' => 850],
                            ['category_id' => $transportCategoryId, 'name' => 'Transportation', 'amount' => 350],
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
                'monthly_repayment' => 120,
                'repayment_start_month' => '2026-08',
                'interim_payment_months' => null,
            ],
            'bnpl' => [
                [
                    'month' => '2026-06',
                    'amount' => 150,
                    'note' => 'Phone',
                ],
                [
                    'month' => '2026-07',
                    'amount' => 150,
                    'note' => 'Phone',
                ],
                [
                    'month' => '2026-08',
                    'amount' => 150,
                    'note' => 'Phone',
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
                'note' => '',
                'compound_interest_enabled' => false,
                'annual_interest_rate_percent' => 0,
            ],
            'epf' => [
                'employee_rate_percent' => 11.0,
                'employer_rate_percent' => 13.0,
            ],
        ];
    }
}
