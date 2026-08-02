<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryCatalog
{
    private const EXPENSE_CATEGORIES = [
        ['id' => 1, 'name' => 'Family'],
        ['id' => 2, 'name' => 'Groceries'],
        ['id' => 3, 'name' => 'Food'],
        ['id' => 4, 'name' => 'Household'],
        ['id' => 5, 'name' => 'Health'],
        ['id' => 6, 'name' => 'Personal Care'],
        ['id' => 7, 'name' => 'IT Product'],
        ['id' => 8, 'name' => 'Prepaid Reload'],
        ['id' => 9, 'name' => 'Transportation'],
        ['id' => 10, 'name' => 'Apparel'],
        ['id' => 11, 'name' => 'Books and Stationery'],
        ['id' => 12, 'name' => 'Fees'],
        ['id' => 13, 'name' => 'Subscriptions'],
        ['id' => 14, 'name' => 'Entertainment'],
        ['id' => 15, 'name' => 'Gifts and Giving'],
        ['id' => 16, 'name' => 'Travel'],
        ['id' => 17, 'name' => 'Payments'],
        ['id' => 18, 'name' => 'Special Projects'],
        ['id' => 19, 'name' => 'Others'],
    ];

    private const INCOME_CATEGORIES = [
        ['id' => 20, 'name' => 'Allowance'],
        ['id' => 21, 'name' => 'PTPTN'],
        ['id' => 22, 'name' => 'Salary'],
        ['id' => 23, 'name' => 'Petty Cash'],
        ['id' => 24, 'name' => 'Bonus'],
        ['id' => 25, 'name' => 'Loans'],
        ['id' => 26, 'name' => 'Payments'],
        ['id' => 27, 'name' => 'Deposit'],
        ['id' => 28, 'name' => 'Money Pot Share'],
        ['id' => 29, 'name' => 'Cash Assistance'],
        ['id' => 30, 'name' => 'Interest'],
        ['id' => 31, 'name' => 'EPF'],
        ['id' => 32, 'name' => 'Fees'],
        ['id' => 33, 'name' => 'Snacktime'],
        ['id' => 34, 'name' => 'Others'],
    ];

    public static function forType(string $type): Collection
    {
        $fallback = collect($type === 'income' ? self::INCOME_CATEGORIES : self::EXPENSE_CATEGORIES)
            ->map(fn (array $category): array => [
                'id' => (int) $category['id'],
                'name' => $category['name'],
            ])
            ->keyBy('id');

        $stored = Category::query()
            ->where('type', $type)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
            ])
            ->keyBy('id');

        return ($stored->isNotEmpty() ? $stored : $fallback)->values();
    }

    public static function normalizeBreakdown(array $breakdown, Collection $categories): array
    {
        $amountByCategory = collect($breakdown)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->mapWithKeys(fn (array $item): array => [
                (int) ($item['category_id'] ?? 0) => max(0, (float) ($item['amount'] ?? 0)),
            ]);

        return $categories
            ->map(fn (array $category): array => [
                'category_id' => (int) $category['id'],
                'name' => $category['name'],
                'amount' => round((float) $amountByCategory->get((int) $category['id'], 0), 2),
            ])
            ->values()
            ->all();
    }

    public static function breakdownTotal(array $breakdown): float
    {
        return round(
            array_reduce(
                $breakdown,
                fn (float $carry, array $item): float => $carry + (float) ($item['amount'] ?? 0),
                0.0
            ),
            2
        );
    }
}
