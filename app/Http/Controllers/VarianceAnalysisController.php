<?php

namespace App\Http\Controllers;

use App\Models\ProjectionActualMonth;
use App\Models\ProjectionScenario;
use App\Models\Setting;
use App\Services\ProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VarianceAnalysisController extends Controller
{
    public function __construct(private readonly ProjectionService $projectionService)
    {
    }

    public function index(): View
    {
        return view('variance-analysis', [
            'theme' => Setting::getValue('theme', 'light'),
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

        $actualMonths = $scenario->actualMonths()
            ->orderBy('month')
            ->get([
                'month',
                'opening_coh',
                'net_income',
                'expenses',
                'debt_servicing',
                'closing_coh',
                'closing_elr',
                'closing_epf',
                'notes',
            ])
            ->map(fn (ProjectionActualMonth $month) => [
                'month' => $month->month,
                'opening_coh' => $month->opening_coh !== null ? (float) $month->opening_coh : null,
                'net_income' => $month->net_income !== null ? (float) $month->net_income : null,
                'expenses' => $month->expenses !== null ? (float) $month->expenses : null,
                'debt_servicing' => $month->debt_servicing !== null ? (float) $month->debt_servicing : null,
                'closing_coh' => $month->closing_coh !== null ? (float) $month->closing_coh : null,
                'closing_elr' => $month->closing_elr !== null ? (float) $month->closing_elr : null,
                'closing_epf' => $month->closing_epf !== null ? (float) $month->closing_epf : null,
                'notes' => $month->notes,
            ])
            ->values();

        return response()->json([
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'notes' => $scenario->notes,
                'updated_at' => $scenario->updated_at?->toDateTimeString(),
            ],
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
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Actual values saved successfully.',
        ]);
    }
}
