<?php

namespace App\Http\Controllers;

use App\Models\HistoryMonth;
use App\Models\Setting;
use App\Support\CategoryCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HistoryController extends Controller
{
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
            'closing_elr' => ['nullable', 'numeric', 'min:0'],
            'closing_epf' => ['nullable', 'numeric', 'min:0'],
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
                'closing_elr' => array_key_exists('closing_elr', $validated) && $validated['closing_elr'] !== null ? round((float) $validated['closing_elr'], 2) : null,
                'closing_epf' => array_key_exists('closing_epf', $validated) && $validated['closing_epf'] !== null ? round((float) $validated['closing_epf'], 2) : null,
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
        return CategoryCatalog::forType($type);
    }

    private function visibleMonths(string $latestMonth): array
    {
        $latest = CarbonImmutable::createFromFormat('Y-m-d', "{$latestMonth}-01")->startOfMonth();

        return collect(range(12, 1))
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
            'closing_elr' => $historyMonth && $historyMonth->closing_elr !== null ? (float) $historyMonth->closing_elr : null,
            'closing_epf' => $historyMonth && $historyMonth->closing_epf !== null ? (float) $historyMonth->closing_epf : null,
            'total_expenses' => $this->breakdownTotal($expenseBreakdown),
            'total_income' => $this->breakdownTotal($incomeBreakdown),
            'expense_breakdown' => $expenseBreakdown,
            'income_breakdown' => $incomeBreakdown,
            'has_record' => $historyMonth !== null,
        ];
    }

    private function normalizeBreakdown(array $breakdown, Collection $categories): array
    {
        return CategoryCatalog::normalizeBreakdown($breakdown, $categories);
    }

    private function breakdownTotal(array $breakdown): float
    {
        return CategoryCatalog::breakdownTotal($breakdown);
    }
}
