<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HistoryCategoryOverride;
use App\Models\HistoryMonth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HistoryLegacyStorageRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_columns_are_removed_and_rollback_reconstructs_existing_balance_months(): void
    {
        $this->assertFalse(Schema::hasColumn('history_months', 'expense_breakdown_json'));
        $this->assertFalse(Schema::hasColumn('history_months', 'income_breakdown_json'));

        $food = Category::query()->create(['name' => 'Food', 'type' => 'expense']);
        $history = HistoryMonth::query()->create(['month' => '2026-06', 'closing_coh' => 1000]);
        HistoryCategoryOverride::query()->create([
            'month' => '2026-06', 'category_id' => $food->id, 'amount' => 25.50,
        ]);
        $migration = require database_path('migrations/2026_08_31_000024_remove_legacy_history_breakdowns.php');

        try {
            $migration->down();

            $stored = DB::table('history_months')->where('id', $history->id)->first();
            $breakdown = json_decode((string) $stored->expense_breakdown_json, true);
            $this->assertSame($food->id, $breakdown[0]['category_id']);
            $this->assertSame(25.5, $breakdown[0]['amount']);
        } finally {
            $migration->up();
        }
    }
}
