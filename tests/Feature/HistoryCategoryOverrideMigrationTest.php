<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryCategoryOverride;
use App\Models\HistoryMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryCategoryOverrideMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_model_stores_absolute_zero_and_optional_note(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);

        $override = HistoryCategoryOverride::query()->create([
            'month' => '2026-06',
            'category_id' => $food->id,
            'amount' => 0,
            'note' => null,
        ]);

        $this->assertSame('0.00', $override->amount);
        $this->assertNull($override->note);
        $this->assertTrue($override->category->is($food));
        $this->assertTrue($food->historyOverrides()->firstOrFail()->is($override));
    }

    public function test_migration_backfills_only_non_zero_legacy_values(): void
    {
        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $salary = Category::query()->create(['name' => 'Salary', 'type' => 'income']);
        HistoryMonth::query()->create([
            'month' => '2026-06',
            'closing_coh' => 1000,
            'expense_breakdown_json' => [
                ['category_id' => $food->id, 'name' => 'Food', 'amount' => 25.50],
            ],
            'income_breakdown_json' => [
                ['category_id' => $salary->id, 'name' => 'Salary', 'amount' => 0],
            ],
        ]);

        $migration = require database_path('migrations/2026_08_31_000023_create_history_category_overrides_table.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('history_category_overrides', [
            'month' => '2026-06',
            'category_id' => $food->id,
            'amount' => 25.50,
            'note' => 'Migrated from legacy History data.',
        ]);
        $this->assertDatabaseMissing('history_category_overrides', [
            'month' => '2026-06',
            'category_id' => $salary->id,
        ]);
    }
}
