<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HistoryMonth;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HistoryController extends Controller
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
    ];

    public function index(): View
    {
        return view('history', [
            'theme' => Setting::getValue('theme', 'light'),
            'latestMonth' => now()->format('Y-m'),
            'expenseCategories' => $this->categories('expense'),
            'incomeCategories' => $this->categories('income'),
        ]);
    }

    public function months(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'latest_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ])->validate();

        $expenseCategories = $this->categories('expense');
        $incomeCategories = $this->categories('income');
        $latestMonth = $validated['latest_month'] ?? now()->format('Y-m');
        $months = $this->visibleMonths($latestMonth);

        $historyRows = HistoryMonth::query()
            ->whereIn('month', $months)
            ->get()
            ->keyBy('month');

        return response()->json([
            'latest_month' => $latestMonth,
            'months' => array_map(
                fn (string $month): array => $this->monthPayload(
                    $month,
                    $historyRows->get($month),
                    $expenseCategories,
                    $incomeCategories
                ),
                $months
            ),
        ]);
    }

    public function saveMonth(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'closing_coh' => ['required', 'numeric'],
            'expense_breakdown' => ['nullable', 'array'],
            'expense_breakdown.*.category_id' => ['required_with:expense_breakdown', 'integer'],
            'expense_breakdown.*.name' => ['required_with:expense_breakdown', 'string', 'max:120'],
            'expense_breakdown.*.amount' => ['required_with:expense_breakdown', 'numeric', 'min:0'],
            'income_breakdown' => ['nullable', 'array'],
            'income_breakdown.*.category_id' => ['required_with:income_breakdown', 'integer'],
            'income_breakdown.*.name' => ['required_with:income_breakdown', 'string', 'max:120'],
            'income_breakdown.*.amount' => ['required_with:income_breakdown', 'numeric', 'min:0'],
        ])->validate();

        $expenseCategories = $this->categories('expense');
        $incomeCategories = $this->categories('income');

        $historyMonth = HistoryMonth::query()->updateOrCreate(
            ['month' => $validated['month']],
            [
                'closing_coh' => round((float) $validated['closing_coh'], 2),
                'expense_breakdown_json' => $this->normalizeBreakdown($validated['expense_breakdown'] ?? [], $expenseCategories),
                'income_breakdown_json' => $this->normalizeBreakdown($validated['income_breakdown'] ?? [], $incomeCategories),
            ]
        );

        return response()->json([
            'message' => 'Data saved successfully.',
            'month' => $this->monthPayload($historyMonth->month, $historyMonth, $expenseCategories, $incomeCategories),
        ]);
    }

    public function updateMonth(Request $request, string $month): JsonResponse
    {
        $request->merge(['month' => $month]);

        return $this->saveMonth($request);
    }

    private function categories(string $type): Collection
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

    private function visibleMonths(string $latestMonth): array
    {
        $latest = CarbonImmutable::createFromFormat('Y-m-d', "{$latestMonth}-01")->startOfMonth();

        return collect(range(11, 1))
            ->map(fn (int $offset): string => $latest->subMonths($offset)->format('Y-m'))
            ->push($latest->format('Y-m'))
            ->values()
            ->all();
    }

    private function monthPayload(string $month, ?HistoryMonth $historyMonth, Collection $expenseCategories, Collection $incomeCategories): array
    {
        $expenseBreakdown = $this->normalizeBreakdown($historyMonth?->expense_breakdown_json ?? [], $expenseCategories);
        $incomeBreakdown = $this->normalizeBreakdown($historyMonth?->income_breakdown_json ?? [], $incomeCategories);

        return [
            'month' => $month,
            'closing_coh' => $historyMonth ? (float) $historyMonth->closing_coh : null,
            'total_expenses' => $this->breakdownTotal($expenseBreakdown),
            'total_income' => $this->breakdownTotal($incomeBreakdown),
            'expense_breakdown' => $expenseBreakdown,
            'income_breakdown' => $incomeBreakdown,
            'has_record' => $historyMonth !== null,
        ];
    }

    private function normalizeBreakdown(array $breakdown, Collection $categories): array
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
                'amount' => round((float) ($amountByCategory->get((int) $category['id'], 0)), 2),
            ])
            ->values()
            ->all();
    }

    private function breakdownTotal(array $breakdown): float
    {
        return round(array_reduce($breakdown, fn (float $carry, array $item): float => $carry + (float) ($item['amount'] ?? 0), 0.0), 2);
    }
}
