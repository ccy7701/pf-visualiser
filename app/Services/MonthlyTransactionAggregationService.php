<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\CategoryCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyTransactionAggregationService
{
    /**
     * @return array<string, array{expense: array<int, array<string, mixed>>, income: array<int, array<string, mixed>>}>
     */
    public function aggregate(array $months, Collection $expenseCategories, Collection $incomeCategories): array
    {
        $months = collect($months)->filter()->unique()->sort()->values();
        if ($months->isEmpty()) {
            return [];
        }

        $amounts = [];
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $months->first().'-01', 'Asia/Kuala_Lumpur')->startOfMonth();
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $months->last().'-01', 'Asia/Kuala_Lumpur')->endOfMonth();

        Transaction::query()
            ->whereBetween('datetime', [$start, $end])
            ->get(['type', 'datetime', 'category_id', 'amount'])
            ->each(function (Transaction $transaction) use (&$amounts, $months): void {
                $month = $transaction->datetime->timezone('Asia/Kuala_Lumpur')->format('Y-m');
                if (! $months->contains($month)) {
                    return;
                }

                $type = $transaction->type;
                $categoryId = (int) $transaction->category_id;
                $amounts[$month][$type][$categoryId] = round(
                    ($amounts[$month][$type][$categoryId] ?? 0) + (float) $transaction->amount,
                    2,
                );
            });

        return $months->mapWithKeys(function (string $month) use ($amounts, $expenseCategories, $incomeCategories): array {
            return [$month => [
                'expense' => $this->normalizeAmounts($amounts[$month]['expense'] ?? [], $expenseCategories),
                'income' => $this->normalizeAmounts($amounts[$month]['income'] ?? [], $incomeCategories),
            ]];
        })->all();
    }

    private function normalizeAmounts(array $amounts, Collection $categories): array
    {
        $breakdown = collect($amounts)
            ->map(fn (float|int $amount, int|string $categoryId): array => [
                'category_id' => (int) $categoryId,
                'amount' => (float) $amount,
            ])
            ->values()
            ->all();

        return CategoryCatalog::normalizeBreakdown($breakdown, $categories);
    }
}
