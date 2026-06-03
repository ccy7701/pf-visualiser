<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryMonth;
use App\Models\ProjectionResultCache;
use App\Models\ProjectionScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VarianceAnalysisModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_variance_analysis_uses_history_expense_breakdown_for_matching_months(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $transport = Category::query()->create(['name' => 'Transportation', 'type' => 'expense']);

        $scenario = ProjectionScenario::query()->create([
            'name' => 'Scenario A',
            'notes' => 'Variance baseline',
            'parameters_json' => [],
        ]);

        ProjectionResultCache::query()->create([
            'scenario_id' => $scenario->id,
            'results_json' => [
                'months' => [
                    [
                        'month' => '2026-06',
                        'opening_coh' => 1000,
                        'net_income' => 2000,
                        'expenses' => 500,
                        'debt_servicing' => 0,
                        'closing_coh' => 2500,
                        'closing_elr' => 300,
                        'closing_epf' => 400,
                    ],
                ],
            ],
        ]);

        HistoryMonth::query()->create([
            'month' => '2026-06',
            'closing_coh' => 1234.56,
            'closing_elr' => 222.22,
            'closing_epf' => 333.33,
            'expense_breakdown_json' => [
                ['category_id' => $food->id, 'name' => 'Food', 'amount' => 321.10],
                ['category_id' => $transport->id, 'name' => 'Transportation', 'amount' => 88.90],
            ],
            'income_breakdown_json' => [],
        ]);

        $response = $this->getJson(route('variance-analysis.scenarios.show', ['scenario' => $scenario]));

        $response->assertOk();
        $response->assertJsonPath('expense_categories.0.id', $food->id);
        $response->assertJsonPath('history_months.0.month', '2026-06');
        $response->assertJsonPath('history_months.0.expense_breakdown.0.category_id', $food->id);
        $response->assertJsonPath('history_months.0.expense_breakdown.0.amount', 321.10);
        $response->assertJsonPath('history_months.0.expense_breakdown.1.category_id', $transport->id);
        $response->assertJsonPath('history_months.0.expense_breakdown.1.amount', 88.90);

        $response->assertJsonPath('actual_months.0.closing_coh', 1234.56);
        $response->assertJsonPath('actual_months.0.closing_elr', 222.22);
        $response->assertJsonPath('actual_months.0.closing_epf', 333.33);
        $response->assertJsonPath('actual_months.0.expenses', 410);
    }
}
