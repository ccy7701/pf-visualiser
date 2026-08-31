<?php

namespace App\Services;

use App\Models\HistoryCategoryOverride;
use App\Support\CategoryCatalog;
use Illuminate\Support\Collection;

class HistoryActivityResolver
{
    public function __construct(private readonly MonthlyTransactionAggregationService $transactionAggregation)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resolve(array $months, Collection $expenseCategories, Collection $incomeCategories): array
    {
        $months = collect($months)->filter()->unique()->sort()->values();
        if ($months->isEmpty()) {
            return [];
        }

        $derived = $this->transactionAggregation->aggregate(
            $months->all(),
            $expenseCategories,
            $incomeCategories,
        );
        $overrides = HistoryCategoryOverride::query()
            ->whereIn('month', $months)
            ->get()
            ->groupBy('month')
            ->map(fn (Collection $rows): Collection => $rows->keyBy('category_id'));

        return $months->mapWithKeys(function (string $month) use ($derived, $overrides): array {
            $monthOverrides = $overrides->get($month, collect());
            $expenseBreakdown = $this->resolveBreakdown($derived[$month]['expense'], $monthOverrides);
            $incomeBreakdown = $this->resolveBreakdown($derived[$month]['income'], $monthOverrides);

            return [$month => [
                'expense_breakdown' => $expenseBreakdown,
                'income_breakdown' => $incomeBreakdown,
                'total_expenses' => CategoryCatalog::breakdownTotal($expenseBreakdown),
                'total_income' => CategoryCatalog::breakdownTotal($incomeBreakdown),
                'has_transactions' => collect($expenseBreakdown)
                    ->merge($incomeBreakdown)
                    ->contains(fn (array $item): bool => $item['derived_amount'] > 0),
                'has_overrides' => $monthOverrides->isNotEmpty(),
            ]];
        })->all();
    }

    private function resolveBreakdown(array $derivedBreakdown, Collection $overrides): array
    {
        return collect($derivedBreakdown)
            ->map(function (array $item) use ($overrides): array {
                $override = $overrides->get((int) $item['category_id']);
                $derivedAmount = round((float) ($item['amount'] ?? 0), 2);
                $overrideAmount = $override === null ? null : round((float) $override->amount, 2);

                return [
                    'category_id' => (int) $item['category_id'],
                    'name' => (string) $item['name'],
                    'amount' => $overrideAmount ?? $derivedAmount,
                    'derived_amount' => $derivedAmount,
                    'override_amount' => $overrideAmount,
                    'override_note' => $override?->note,
                    'is_overridden' => $override !== null,
                ];
            })
            ->values()
            ->all();
    }
}
