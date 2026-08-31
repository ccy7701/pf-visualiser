<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history_category_overrides', function (Blueprint $table): void {
            $table->id();
            $table->string('month', 7);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['month', 'category_id']);
            $table->index('month');
        });

        if (! Schema::hasTable('history_months')) {
            return;
        }

        DB::table('history_months')
            ->orderBy('id')
            ->each(function (object $historyMonth): void {
                $this->migrateBreakdown($historyMonth, 'expense', 'expense_breakdown_json');
                $this->migrateBreakdown($historyMonth, 'income', 'income_breakdown_json');
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('history_category_overrides');
    }

    private function migrateBreakdown(object $historyMonth, string $type, string $column): void
    {
        if (! property_exists($historyMonth, $column) || $historyMonth->{$column} === null) {
            return;
        }

        $items = json_decode((string) $historyMonth->{$column}, true);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (! is_array($item) || (float) ($item['amount'] ?? 0) <= 0) {
                continue;
            }

            $categoryId = $this->resolveCategoryId($item, $type);
            if ($categoryId === null) {
                continue;
            }

            $now = now();
            DB::table('history_category_overrides')->updateOrInsert(
                ['month' => (string) $historyMonth->month, 'category_id' => $categoryId],
                [
                    'amount' => round((float) $item['amount'], 2),
                    'note' => 'Migrated from legacy History data.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function resolveCategoryId(array $item, string $type): ?int
    {
        $categoryId = (int) ($item['category_id'] ?? 0);
        if ($categoryId > 0 && DB::table('categories')->where('id', $categoryId)->where('type', $type)->exists()) {
            return $categoryId;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $existingId = DB::table('categories')->where('type', $type)->where('name', $name)->value('id');
        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('categories')->insertGetId([
            'name' => $name,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
