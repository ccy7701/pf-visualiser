<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryMonth;
use App\Models\HistoryCategoryOverride;
use App\Models\ProjectionResultCache;
use App\Models\ProjectionScenario;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VarianceAnalysisModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_variance_analysis_uses_resolved_transaction_activity_for_matching_months(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $transport = Category::query()->create(['name' => 'Transportation', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);

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
                    [
                        'month' => '2026-07',
                        'opening_coh' => 2500,
                        'net_income' => 2000,
                        'expenses' => 500,
                        'debt_servicing' => 0,
                        'closing_coh' => 4000,
                        'closing_elr' => 300,
                        'closing_epf' => 400,
                    ],
                ],
            ],
        ]);

        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-10 10:00:00', 'category_id' => $food->id, 'amount' => 300,
        ]);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-11 10:00:00', 'category_id' => $transport->id, 'amount' => 88.90,
        ]);
        Transaction::query()->create([
            'type' => 'income', 'datetime' => '2026-06-15 10:00:00', 'category_id' => $salary->id, 'amount' => 2200,
        ]);
        Transaction::query()->create([
            'type' => 'income', 'datetime' => '2026-07-15 10:00:00', 'category_id' => $salary->id, 'amount' => 2300,
        ]);
        HistoryCategoryOverride::query()->create([
            'month' => '2026-06', 'category_id' => $food->id, 'amount' => 321.10, 'note' => 'Reconciled',
        ]);

        HistoryMonth::query()->create([
            'month' => '2026-06',
            'closing_coh' => 1234.56,
            'closing_elr' => 222.22,
            'closing_epf' => 333.33,
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
        $response->assertJsonPath('actual_months.0.net_income', 2200);
        $response->assertJsonPath('actual_months.0.has_overrides', true);
        $response->assertJsonPath('actual_months.1.month', '2026-07');
        $response->assertJsonPath('actual_months.1.net_income', 2300);
        $response->assertJsonPath('actual_months.1.closing_coh', null);
        $response->assertJsonPath('actual_months.1.has_balance_record', false);
    }
}
