<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_page_loads_saves_and_returns_month_window(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-10 10:00:00', 'category_id' => $food->id, 'amount' => 321.10,
        ]);
        Transaction::query()->create([
            'type' => 'income', 'datetime' => '2026-06-15 10:00:00', 'category_id' => $salary->id, 'amount' => 2200,
        ]);

        $this->get(route('history.index'))
            ->assertOk()
            ->assertSee('class="history-waffle-chart"', false)
            ->assertSee('Save Balances')
            ->assertSee('Save Overrides')
            ->assertSee('Amounts come from the Transaction Log.')
            ->assertDontSee('<canvas id="historyExpenseCategoryChart"', false);

        $windowResponse = $this->getJson(route('history.months', ['latest_month' => '2026-06']));
        $windowResponse->assertOk();
        $windowResponse->assertJsonCount(12, 'months');
        $windowResponse->assertJsonPath('months.0.month', '2025-07');
        $windowResponse->assertJsonPath('months.11.month', '2026-06');
        $windowResponse->assertJsonPath('months.11.has_balance_record', false);
        $windowResponse->assertJsonPath('months.11.has_transactions', true);
        $windowResponse->assertJsonPath('months.11.total_expenses', 321.10);
        $windowResponse->assertJsonPath('months.11.total_income', 2200);

        $saveResponse = $this->postJson(route('history.months.save'), [
            'month' => '2026-06',
            'closing_coh' => 1234.56,
            'closing_elr' => 200.25,
            'closing_epf' => 300.75,
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

    public function test_closed_month_overrides_can_be_saved_replaced_and_cleared(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 12:00:00', 'Asia/Kuala_Lumpur'));
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-10 10:00:00', 'category_id' => $food->id, 'amount' => 40,
        ]);

        $save = $this->putJson(route('history.months.overrides.save', ['month' => '2026-06', 'type' => 'expense']), [
            'overrides' => [['category_id' => $food->id, 'amount' => 0, 'note' => 'Excluded after reconciliation']],
        ]);

        $save->assertOk()
            ->assertJsonPath('month.total_expenses', 0)
            ->assertJsonPath('month.expense_breakdown.0.derived_amount', 40)
            ->assertJsonPath('month.expense_breakdown.0.override_amount', 0)
            ->assertJsonPath('month.expense_breakdown.0.is_overridden', true);
        $this->assertDatabaseHas('history_category_overrides', [
            'month' => '2026-06', 'category_id' => $food->id, 'amount' => 0,
        ]);

        $clear = $this->putJson(route('history.months.overrides.save', ['month' => '2026-06', 'type' => 'expense']), [
            'overrides' => [],
        ]);

        $clear->assertOk()
            ->assertJsonPath('month.total_expenses', 40)
            ->assertJsonPath('month.expense_breakdown.0.is_overridden', false);
        $this->assertDatabaseCount('history_category_overrides', 0);
    }

    public function test_override_validation_enforces_type_and_closed_month(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 12:00:00', 'Asia/Kuala_Lumpur'));
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);

        $this->putJson(route('history.months.overrides.save', ['month' => '2026-06', 'type' => 'expense']), [
            'overrides' => [['category_id' => $salary->id, 'amount' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('overrides');

        $this->putJson(route('history.months.overrides.save', ['month' => '2026-08', 'type' => 'income']), [
            'overrides' => [['category_id' => $salary->id, 'amount' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('month');

        $this->assertDatabaseCount('history_category_overrides', 0);
    }
}
