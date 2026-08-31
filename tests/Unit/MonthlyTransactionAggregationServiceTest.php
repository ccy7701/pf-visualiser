<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Services\MonthlyTransactionAggregationService;
use App\Support\CategoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyTransactionAggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_transactions_by_month_type_and_parent_category(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        $meal = Subcategory::query()->create(['category_id' => $food->id, 'name' => 'Meal']);

        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-01 00:00:00', 'category_id' => $food->id,
            'subcategory_id' => $meal->id, 'amount' => 12.40, 'is_bnpl' => false,
        ]);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-30 23:59:59', 'category_id' => $food->id,
            'amount' => 87.60, 'is_bnpl' => true,
        ]);
        Transaction::query()->create([
            'type' => 'income', 'datetime' => '2026-06-15 12:00:00', 'category_id' => $salary->id,
            'amount' => 2500,
        ]);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-07-01 00:00:00', 'category_id' => $food->id,
            'amount' => 20,
        ]);

        $result = app(MonthlyTransactionAggregationService::class)->aggregate(
            ['2026-06', '2026-07'],
            CategoryCatalog::forType('expense'),
            CategoryCatalog::forType('income'),
        );

        $this->assertSame(100.0, $result['2026-06']['expense'][0]['amount']);
        $this->assertSame(2500.0, $result['2026-06']['income'][0]['amount']);
        $this->assertSame(20.0, $result['2026-07']['expense'][0]['amount']);
        $this->assertSame(0.0, $result['2026-07']['income'][0]['amount']);
    }

    public function test_it_returns_zero_filled_breakdowns_for_empty_months(): void
    {
        Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        Category::query()->create(['name' => 'Salary', 'type' => 'income']);

        $result = app(MonthlyTransactionAggregationService::class)->aggregate(
            ['2026-06'],
            CategoryCatalog::forType('expense'),
            CategoryCatalog::forType('income'),
        );

        $this->assertSame(0.0, $result['2026-06']['expense'][0]['amount']);
        $this->assertSame(0.0, $result['2026-06']['income'][0]['amount']);
    }
}
