<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_months', function (Blueprint $table): void {
            $table->dropColumn(['expense_breakdown_json', 'income_breakdown_json']);
        });
    }

    public function down(): void
    {
        Schema::table('history_months', function (Blueprint $table): void {
            $table->json('expense_breakdown_json')->nullable();
            $table->json('income_breakdown_json')->nullable();
        });

        if (! Schema::hasTable('history_category_overrides')) {
            return;
        }

        DB::table('history_months')->orderBy('id')->each(function (object $historyMonth): void {
            $breakdowns = DB::table('history_category_overrides')
                ->join('categories', 'categories.id', '=', 'history_category_overrides.category_id')
                ->where('history_category_overrides.month', $historyMonth->month)
                ->get([
                    'history_category_overrides.category_id',
                    'history_category_overrides.amount',
                    'categories.name',
                    'categories.type',
                ])
                ->groupBy('type');

            DB::table('history_months')->where('id', $historyMonth->id)->update([
                'expense_breakdown_json' => $this->encodeBreakdown($breakdowns->get('expense', collect())),
                'income_breakdown_json' => $this->encodeBreakdown($breakdowns->get('income', collect())),
            ]);
        });
    }

    private function encodeBreakdown(iterable $rows): string
    {
        return json_encode(collect($rows)->map(fn (object $row): array => [
            'category_id' => (int) $row->category_id,
            'name' => (string) $row->name,
            'amount' => round((float) $row->amount, 2),
        ])->values()->all(), JSON_THROW_ON_ERROR);
    }
};
