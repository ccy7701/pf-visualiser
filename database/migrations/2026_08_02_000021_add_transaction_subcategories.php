<?php

use Database\Seeders\SubcategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('subcategory_id')
                ->nullable()
                ->after('category_id')
                ->constrained()
                ->nullOnDelete();
        });

        foreach (['income', 'expense'] as $type) {
            $this->consolidateOtherCategories($type);
        }

        (new SubcategorySeeder)->run();
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subcategory_id');
        });

        Schema::dropIfExists('subcategories');
    }

    private function consolidateOtherCategories(string $type): void
    {
        $aliases = DB::table('categories')
            ->where('type', $type)
            ->whereIn(DB::raw('LOWER(name)'), ['other', 'others', 'modified bal.', 'modified balance'])
            ->orderByRaw("CASE WHEN LOWER(name) = 'others' THEN 0 WHEN LOWER(name) = 'other' THEN 1 ELSE 2 END")
            ->get(['id', 'name']);

        if ($aliases->isEmpty()) {
            return;
        }

        $target = $aliases->first();
        DB::table('categories')->where('id', $target->id)->update(['name' => 'Others']);

        foreach ($aliases->skip(1) as $source) {
            DB::table('transactions')
                ->where('category_id', $source->id)
                ->update([
                    'category_id' => $target->id,
                    'subcategory_id' => null,
                ]);

            $this->replaceCategoryIdInJsonColumns((int) $source->id, (int) $target->id, $type);
            DB::table('categories')->where('id', $source->id)->delete();
        }
    }

    private function replaceCategoryIdInJsonColumns(int $sourceId, int $targetId, string $type): void
    {
        $columns = $type === 'expense'
            ? [['history_months', 'expense_breakdown_json'], ['projection_actual_months', 'expense_breakdown_json']]
            : [['history_months', 'income_breakdown_json']];

        foreach ($columns as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->whereNotNull($column)->orderBy('id')->each(function (object $row) use ($table, $column, $sourceId, $targetId): void {
                $items = json_decode((string) $row->{$column}, true);
                if (! is_array($items)) {
                    return;
                }

                $byCategory = [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $categoryId = (int) ($item['category_id'] ?? 0);
                    if ($categoryId === $sourceId) {
                        $categoryId = $targetId;
                        $item['category_id'] = $targetId;
                        $item['name'] = 'Others';
                    }

                    if (isset($byCategory[$categoryId])) {
                        $byCategory[$categoryId]['amount'] = round(
                            (float) ($byCategory[$categoryId]['amount'] ?? 0) + (float) ($item['amount'] ?? 0),
                            2,
                        );
                    } else {
                        $byCategory[$categoryId] = $item;
                    }
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(array_values($byCategory)),
                ]);
            });
        }

        if (! Schema::hasTable('projection_scenarios') || ! Schema::hasColumn('projection_scenarios', 'parameters_json')) {
            return;
        }

        DB::table('projection_scenarios')->orderBy('id')->each(function (object $row) use ($sourceId, $targetId): void {
            $parameters = json_decode((string) $row->parameters_json, true);
            if (! is_array($parameters)) {
                return;
            }

            $replace = function (mixed $value) use (&$replace, $sourceId, $targetId): mixed {
                if (! is_array($value)) {
                    return $value;
                }

                if ((int) ($value['category_id'] ?? 0) === $sourceId) {
                    $value['category_id'] = $targetId;
                    if (array_key_exists('name', $value)) {
                        $value['name'] = 'Others';
                    }
                }

                foreach ($value as $key => $child) {
                    $value[$key] = $replace($child);
                }

                return $value;
            };

            DB::table('projection_scenarios')->where('id', $row->id)->update([
                'parameters_json' => json_encode($replace($parameters)),
            ]);
        });
    }
};
