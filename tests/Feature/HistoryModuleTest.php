<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_page_loads_saves_and_returns_month_window(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);

        $this->get(route('history.index'))->assertOk();

        $windowResponse = $this->getJson(route('history.months', ['latest_month' => '2026-06']));
        $windowResponse->assertOk();
        $windowResponse->assertJsonCount(12, 'months');
        $windowResponse->assertJsonPath('months.0.month', '2025-07');
        $windowResponse->assertJsonPath('months.11.month', '2026-06');

        $saveResponse = $this->postJson(route('history.months.save'), [
            'month' => '2026-06',
            'closing_coh' => 1234.56,
            'closing_elr' => 200.25,
            'closing_epf' => 300.75,
            'expense_breakdown' => [
                ['category_id' => $food->id, 'name' => 'Food', 'amount' => 321.10],
            ],
            'income_breakdown' => [
                ['category_id' => $salary->id, 'name' => 'Salary', 'amount' => 2200],
            ],
        ]);

        $saveResponse->assertOk();
        $saveResponse->assertJsonPath('month.month', '2026-06');
        $saveResponse->assertJsonPath('month.closing_coh', 1234.56);
        $saveResponse->assertJsonPath('month.closing_elr', 200.25);
        $saveResponse->assertJsonPath('month.closing_epf', 300.75);
        $saveResponse->assertJsonPath('month.total_expenses', 321.10);
        $saveResponse->assertJsonPath('month.total_income', 2200);

        $this->assertDatabaseHas('history_months', [
            'month' => '2026-06',
            'closing_coh' => 1234.56,
            'closing_elr' => 200.25,
            'closing_epf' => 300.75,
        ]);

        $reloadResponse = $this->getJson(route('history.months', ['latest_month' => '2026-06']));
        $reloadResponse->assertOk();
        $reloadResponse->assertJsonPath('months.11.has_record', true);
        $reloadResponse->assertJsonPath('months.11.total_expenses', 321.10);
        $reloadResponse->assertJsonPath('months.11.total_income', 2200);
    }
}
