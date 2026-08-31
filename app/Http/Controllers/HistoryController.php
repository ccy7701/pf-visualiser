<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HistoryCategoryOverride;
use App\Models\HistoryMonth;
use App\Models\Setting;
use App\Services\HistoryActivityResolver;
use App\Support\CategoryCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __construct(private readonly HistoryActivityResolver $activityResolver)
    {
    }

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
        $activity = $this->activityResolver->resolve($months, $expenseCategories, $incomeCategories);

        return response()->json([
            'latest_month' => $latestMonth,
            'months' => array_map(
                fn (string $month): array => $this->monthPayload(
                    $month,
                    $historyRows->get($month),
                    $activity[$month],
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
        ])->validate();

        $expenseCategories = $this->categories('expense');
        $incomeCategories = $this->categories('income');

        $historyMonth = HistoryMonth::query()->updateOrCreate(
            ['month' => $validated['month']],
            [
                'closing_coh' => round((float) $validated['closing_coh'], 2),
                'closing_elr' => array_key_exists('closing_elr', $validated) && $validated['closing_elr'] !== null ? round((float) $validated['closing_elr'], 2) : null,
                'closing_epf' => array_key_exists('closing_epf', $validated) && $validated['closing_epf'] !== null ? round((float) $validated['closing_epf'], 2) : null,
            ]
        );
        $activity = $this->activityResolver->resolve(
            [$historyMonth->month],
            $expenseCategories,
            $incomeCategories,
        )[$historyMonth->month];

        return response()->json([
            'message' => 'Balances saved successfully.',
            'month' => $this->monthPayload($historyMonth->month, $historyMonth, $activity),
        ]);
    }

    public function updateMonth(Request $request, string $month): JsonResponse
    {
        $request->merge(['month' => $month]);

        return $this->saveMonth($request);
    }

    public function saveOverrides(Request $request, string $month, string $type): JsonResponse
    {
        validator(['type' => $type], ['type' => ['required', Rule::in(['income', 'expense'])]])->validate();
        $validated = validator($request->all(), [
            'overrides' => ['present', 'array'],
            'overrides.*.category_id' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'overrides.*.amount' => ['required', 'numeric', 'min:0'],
            'overrides.*.note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw ValidationException::withMessages(['month' => 'The month must use YYYY-MM format.']);
        }

        if ($month >= now('Asia/Kuala_Lumpur')->format('Y-m')) {
            throw ValidationException::withMessages(['month' => 'Overrides are only available for closed months.']);
        }

        $categoryIds = collect($validated['overrides'])->pluck('category_id')->map(fn ($id): int => (int) $id);
        $validCategoryIds = Category::query()->where('type', $type)->whereIn('id', $categoryIds)->pluck('id');
        if ($validCategoryIds->count() !== $categoryIds->count()) {
            throw ValidationException::withMessages(['overrides' => 'Every override category must match the selected transaction type.']);
        }

        DB::transaction(function () use ($month, $type, $validated): void {
            $typeCategoryIds = Category::query()->where('type', $type)->pluck('id');
            HistoryCategoryOverride::query()
                ->where('month', $month)
                ->whereIn('category_id', $typeCategoryIds)
                ->delete();

            foreach ($validated['overrides'] as $override) {
                HistoryCategoryOverride::query()->create([
                    'month' => $month,
                    'category_id' => (int) $override['category_id'],
                    'amount' => round((float) $override['amount'], 2),
                    'note' => filled($override['note'] ?? null) ? trim((string) $override['note']) : null,
                ]);
            }
        });

        $expenseCategories = $this->categories('expense');
        $incomeCategories = $this->categories('income');
        $activity = $this->activityResolver->resolve([$month], $expenseCategories, $incomeCategories)[$month];

        return response()->json([
            'message' => ucfirst($type).' overrides saved successfully.',
            'month' => $this->monthPayload($month, HistoryMonth::query()->where('month', $month)->first(), $activity),
        ]);
    }

    private function categories(string $type): Collection
    {
        return CategoryCatalog::forType($type);
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

    private function monthPayload(string $month, ?HistoryMonth $historyMonth, array $activity): array
    {
        return [
            'month' => $month,
            'closing_coh' => $historyMonth ? (float) $historyMonth->closing_coh : null,
            'closing_elr' => $historyMonth && $historyMonth->closing_elr !== null ? (float) $historyMonth->closing_elr : null,
            'closing_epf' => $historyMonth && $historyMonth->closing_epf !== null ? (float) $historyMonth->closing_epf : null,
            'total_expenses' => $activity['total_expenses'],
            'total_income' => $activity['total_income'],
            'expense_breakdown' => $activity['expense_breakdown'],
            'income_breakdown' => $activity['income_breakdown'],
            'has_balance_record' => $historyMonth !== null,
            'has_transactions' => $activity['has_transactions'],
            'has_overrides' => $activity['has_overrides'],
            'has_record' => $historyMonth !== null,
        ];
    }
}
