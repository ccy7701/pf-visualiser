<?php

namespace App\Http\Controllers;

use App\Models\HistoryMonth;
use App\Models\ProjectionActualMonth;
use App\Models\ProjectionScenario;
use App\Models\Setting;
use App\Services\HistoryActivityResolver;
use App\Support\CategoryCatalog;
use App\Services\ProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VarianceAnalysisController extends Controller
{
    public function __construct(
        private readonly ProjectionService $projectionService,
        private readonly HistoryActivityResolver $activityResolver,
    ) {
    }

    public function index(): View
    {
        return view('variance-analysis', [
            'theme' => Setting::getValue('theme', 'light'),
            'expenseCategories' => CategoryCatalog::forType('expense'),
            'scenarios' => ProjectionScenario::query()
                ->latest('updated_at')
                ->limit(50)
                ->get(['id', 'name', 'notes', 'updated_at']),
        ]);
    }

    public function showScenario(ProjectionScenario $scenario): JsonResponse
    {
        $result = $scenario->resultCache?->results_json;

        if (! is_array($result)) {
            $payload = is_array($scenario->parameters_json) ? $scenario->parameters_json : [];
            $result = $this->projectionService->project($payload);
            $scenario->resultCache()->updateOrCreate([], ['results_json' => $result]);
        }

        $projectedMonths = array_values(array_map(function (array $row): array {
            return [
                'month' => (string) ($row['month'] ?? ''),
                'opening_coh' => (float) ($row['opening_coh'] ?? 0),
                'net_income' => (float) ($row['net_income'] ?? 0),
                'expenses' => (float) ($row['expenses'] ?? 0),
                'debt_servicing' => (float) ($row['debt_servicing'] ?? 0),
                'closing_coh' => (float) ($row['closing_coh'] ?? 0),
                'closing_elr' => (float) ($row['closing_elr'] ?? 0),
                'closing_epf' => (float) ($row['closing_epf'] ?? 0),
            ];
        }, array_filter($result['months'] ?? [], fn ($row) => is_array($row))));
        $expenseCategories = CategoryCatalog::forType('expense');
        $projectedMonthKeys = collect($projectedMonths)
            ->pluck('month')
            ->filter()
            ->values();
        $incomeCategories = CategoryCatalog::forType('income');
        $activityByMonth = $this->activityResolver->resolve(
            $projectedMonthKeys->all(),
            $expenseCategories,
            $incomeCategories,
        );
        $historyRows = HistoryMonth::query()
            ->whereIn('month', $projectedMonthKeys)
            ->get(['month', 'closing_coh', 'closing_elr', 'closing_epf'])
            ->keyBy('month');
        $actualMonthKeys = $projectedMonthKeys
            ->filter(function (string $month) use ($activityByMonth, $historyRows): bool {
                $activity = $activityByMonth[$month];

                return $historyRows->has($month) || $activity['has_transactions'] || $activity['has_overrides'];
            });
        $historyByMonth = $actualMonthKeys
            ->map(fn (string $month): array => [
                'month' => $month,
                'expense_breakdown' => $activityByMonth[$month]['expense_breakdown'],
                'income_breakdown' => $activityByMonth[$month]['income_breakdown'],
                'has_balance_record' => $historyRows->has($month),
                'has_transactions' => $activityByMonth[$month]['has_transactions'],
                'has_overrides' => $activityByMonth[$month]['has_overrides'],
            ])
            ->values();

        $actualMonths = $actualMonthKeys
            ->sort()
            ->map(function (string $month) use ($activityByMonth, $historyRows): array {
                $history = $historyRows->get($month);
                $activity = $activityByMonth[$month];

                return [
                    'month' => $month,
                    'opening_coh' => null,
                    'net_income' => $activity['total_income'],
                    'expenses' => $activity['total_expenses'],
                    'debt_servicing' => null,
                    'closing_coh' => $history?->closing_coh !== null ? (float) $history->closing_coh : null,
                    'closing_elr' => $history?->closing_elr !== null ? (float) $history->closing_elr : null,
                    'closing_epf' => $history?->closing_epf !== null ? (float) $history->closing_epf : null,
                    'expense_breakdown' => $activity['expense_breakdown'],
                    'notes' => null,
                    'has_balance_record' => $history !== null,
                    'has_transactions' => $activity['has_transactions'],
                    'has_overrides' => $activity['has_overrides'],
                ];
            })
            ->values();

        return response()->json([
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'notes' => $scenario->notes,
                'updated_at' => $scenario->updated_at?->toDateTimeString(),
            ],
            'expense_categories' => $expenseCategories->values(),
            'history_months' => $historyByMonth,
            'projected_months' => $projectedMonths,
            'actual_months' => $actualMonths,
        ]);
    }

    public function saveActuals(Request $request, ProjectionScenario $scenario): JsonResponse
    {
        $validated = validator($request->all(), [
            'actuals' => ['required', 'array'],
            'actuals.*.month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'actuals.*.opening_coh' => ['nullable', 'numeric'],
            'actuals.*.net_income' => ['nullable', 'numeric'],
            'actuals.*.expenses' => ['nullable', 'numeric'],
            'actuals.*.debt_servicing' => ['nullable', 'numeric'],
            'actuals.*.closing_coh' => ['nullable', 'numeric'],
            'actuals.*.closing_elr' => ['nullable', 'numeric', 'min:0'],
            'actuals.*.closing_epf' => ['nullable', 'numeric', 'min:0'],
            'actuals.*.expense_breakdown' => ['nullable', 'array'],
            'actuals.*.expense_breakdown.*.category_id' => ['required_with:actuals.*.expense_breakdown', 'string', 'max:120'],
            'actuals.*.expense_breakdown.*.name' => ['required_with:actuals.*.expense_breakdown', 'string', 'max:120'],
            'actuals.*.expense_breakdown.*.amount' => ['required_with:actuals.*.expense_breakdown', 'numeric', 'min:0'],
            'actuals.*.notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        foreach ($validated['actuals'] as $row) {
            ProjectionActualMonth::query()->updateOrCreate(
                [
                    'scenario_id' => $scenario->id,
                    'month' => $row['month'],
                ],
                [
                    'opening_coh' => $row['opening_coh'] ?? null,
                    'net_income' => $row['net_income'] ?? null,
                    'expenses' => $row['expenses'] ?? null,
                    'debt_servicing' => $row['debt_servicing'] ?? null,
                    'closing_coh' => $row['closing_coh'] ?? null,
                    'closing_elr' => $row['closing_elr'] ?? null,
                    'closing_epf' => $row['closing_epf'] ?? null,
                    'expense_breakdown_json' => $row['expense_breakdown'] ?? [],
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Actual values saved successfully.',
        ]);
    }
}
