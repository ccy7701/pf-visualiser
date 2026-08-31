<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\HistoryCategoryOverride;
use App\Models\Transaction;
use App\Services\HistoryActivityResolver;
use App\Support\CategoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryActivityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_combines_derived_and_absolute_override_amounts(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $travel = Category::query()->create(['name' => 'Travel', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-10 10:00:00', 'category_id' => $food->id, 'amount' => 40,
        ]);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-11 10:00:00', 'category_id' => $travel->id, 'amount' => 60,
        ]);
        Transaction::query()->create([
            'type' => 'income', 'datetime' => '2026-06-15 10:00:00', 'category_id' => $salary->id, 'amount' => 2000,
        ]);
        HistoryCategoryOverride::query()->create([
            'month' => '2026-06', 'category_id' => $food->id, 'amount' => 55, 'note' => 'Reconciled receipt',
        ]);

        $result = $this->resolve('2026-06');
        $foodResult = collect($result['expense_breakdown'])->firstWhere('category_id', $food->id);
        $travelResult = collect($result['expense_breakdown'])->firstWhere('category_id', $travel->id);

        $this->assertSame(40.0, $foodResult['derived_amount']);
        $this->assertSame(55.0, $foodResult['override_amount']);
        $this->assertSame(55.0, $foodResult['amount']);
        $this->assertSame('Reconciled receipt', $foodResult['override_note']);
        $this->assertTrue($foodResult['is_overridden']);
        $this->assertSame(60.0, $travelResult['amount']);
        $this->assertFalse($travelResult['is_overridden']);
        $this->assertSame(115.0, $result['total_expenses']);
        $this->assertSame(2000.0, $result['total_income']);
        $this->assertTrue($result['has_transactions']);
        $this->assertTrue($result['has_overrides']);
    }

    public function test_explicit_zero_override_is_preserved_and_deletion_restores_derived_amount(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        Transaction::query()->create([
            'type' => 'expense', 'datetime' => '2026-06-10 10:00:00', 'category_id' => $food->id, 'amount' => 40,
        ]);
        $override = HistoryCategoryOverride::query()->create([
            'month' => '2026-06', 'category_id' => $food->id, 'amount' => 0,
        ]);

        $overridden = collect($this->resolve('2026-06')['expense_breakdown'])->firstWhere('category_id', $food->id);
        $this->assertTrue($overridden['is_overridden']);
        $this->assertSame(0.0, $overridden['amount']);

        $override->delete();

        $restored = collect($this->resolve('2026-06')['expense_breakdown'])->firstWhere('category_id', $food->id);
        $this->assertFalse($restored['is_overridden']);
        $this->assertSame(40.0, $restored['amount']);
    }

    private function resolve(string $month): array
    {
        return app(HistoryActivityResolver::class)->resolve(
            [$month],
            CategoryCatalog::forType('expense'),
            CategoryCatalog::forType('income'),
        )[$month];
    }
}
