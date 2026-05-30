<?php

namespace App\Http\Controllers;

use App\Models\ProjectionScenario;
use App\Services\ProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class ProjectionController extends Controller
{
    public function __construct(private readonly ProjectionService $projectionService)
    {
    }

    public function index(): View
    {
        return view('projection', [
            'scenarios' => ProjectionScenario::query()
                ->latest('updated_at')
                ->limit(30)
                ->get(['id', 'name', 'notes', 'created_at', 'updated_at']),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = validator($request->all(), $this->projectionRules())->validate();

        try {
            $result = $this->projectionService->project($validated);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payload' => $e->getMessage()]);
        }

        return response()->json($result);
    }

    public function saveScenario(Request $request): JsonResponse
    {
        $validated = validator($request->all(), array_merge([
            'scenario_id' => ['nullable', 'integer', 'exists:projection_scenarios,id'],
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->projectionRules()))->validate();

        try {
            $normalizedPayload = $this->projectionService->normalizePayload($validated);
            $result = $this->projectionService->project($normalizedPayload);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payload' => $e->getMessage()]);
        }

        $scenarioId = Arr::get($validated, 'scenario_id');
        $scenario = $scenarioId
            ? ProjectionScenario::query()->findOrFail((int) $scenarioId)
            : new ProjectionScenario();

        $scenario->fill([
            'name' => $validated['name'],
            'notes' => Arr::get($validated, 'notes'),
            'parameters_json' => $normalizedPayload,
        ]);
        $scenario->save();

        $scenario->resultCache()->updateOrCreate([], ['results_json' => $result]);

        return response()->json([
            'message' => $scenarioId ? 'Scenario updated successfully.' : 'Scenario saved successfully.',
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'notes' => $scenario->notes,
                'created_at' => $scenario->created_at?->toDateTimeString(),
                'updated_at' => $scenario->updated_at?->toDateTimeString(),
            ],
            'result' => $result,
        ]);
    }

    public function showScenario(ProjectionScenario $scenario): JsonResponse
    {
        $payload = $scenario->parameters_json ?? [];

        if (! is_array($payload)) {
            $payload = [];
        }

        $result = $scenario->resultCache?->results_json;

        if (! is_array($result)) {
            $result = $this->projectionService->project($payload);
            $scenario->resultCache()->updateOrCreate([], ['results_json' => $result]);
        }

        return response()->json([
            'scenario' => [
                'id' => $scenario->id,
                'name' => $scenario->name,
                'notes' => $scenario->notes,
                'parameters_json' => $payload,
                'created_at' => $scenario->created_at?->toDateTimeString(),
                'updated_at' => $scenario->updated_at?->toDateTimeString(),
            ],
            'result' => $result,
        ]);
    }

    public function destroyScenario(ProjectionScenario $scenario): JsonResponse
    {
        $scenario->delete();

        return response()->json([
            'message' => 'Scenario deleted successfully.',
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'scenario_ids' => ['required', 'array', 'min:2', 'max:4'],
            'scenario_ids.*' => ['required', 'integer', 'exists:projection_scenarios,id'],
        ])->validate();

        $ids = array_values(array_unique(array_map('intval', $validated['scenario_ids'])));
        $scenarios = ProjectionScenario::query()->whereIn('id', $ids)->with('resultCache')->get()->keyBy('id');

        $comparisons = [];

        foreach ($ids as $id) {
            $scenario = $scenarios->get($id);

            if (! $scenario) {
                continue;
            }

            $payload = is_array($scenario->parameters_json) ? $scenario->parameters_json : [];
            $result = $scenario->resultCache?->results_json;

            if (! is_array($result)) {
                $result = $this->projectionService->project($payload);
                $scenario->resultCache()->updateOrCreate([], ['results_json' => $result]);
            }

            $comparisons[] = [
                'scenario' => [
                    'id' => $scenario->id,
                    'name' => $scenario->name,
                    'notes' => $scenario->notes,
                    'updated_at' => $scenario->updated_at?->toDateTimeString(),
                ],
                'result' => $result,
            ];
        }

        return response()->json(['comparisons' => $comparisons]);
    }

    private function projectionRules(): array
    {
        return [
            'scenario' => ['required', 'array'],
            'scenario.start_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'scenario.end_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'scenario.starting_coh' => ['required', 'numeric'],
            'scenario.starting_elr' => ['required', 'numeric', 'min:0'],
            'scenario.starting_epf' => ['required', 'numeric', 'min:0'],

            'employment' => ['required', 'array'],
            'employment.probation_salary' => ['required', 'numeric', 'min:0'],
            'employment.confirmed_salary' => ['required', 'numeric', 'min:0'],
            'employment.probation_duration_months' => ['required', 'integer', 'min:0', 'max:120'],
            'employment.salary_start_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'employment.salary_paid_in_arrears' => ['required', 'boolean'],

            'cost_of_living' => ['required', 'array'],
            'cost_of_living.budgets' => ['required', 'array'],
            'cost_of_living.budgets.bcol' => ['required', 'array'],
            'cost_of_living.budgets.fcol_lite' => ['required', 'array'],
            'cost_of_living.budgets.fcol_max' => ['required', 'array'],
            'cost_of_living.budgets.*.category_allocations' => ['required', 'array'],
            'cost_of_living.budgets.*.category_allocations.*.category_id' => ['nullable'],
            'cost_of_living.budgets.*.category_allocations.*.name' => ['required', 'string', 'max:120'],
            'cost_of_living.budgets.*.category_allocations.*.amount' => ['required', 'numeric', 'min:0'],
            'cost_of_living.monthly_budget_selection' => ['nullable', 'array'],
            'cost_of_living.monthly_budget_selection.*.month' => ['required_with:cost_of_living.monthly_budget_selection', 'regex:/^\d{4}-\d{2}$/'],
            'cost_of_living.monthly_budget_selection.*.budget' => ['required_with:cost_of_living.monthly_budget_selection', 'in:bcol,fcol_lite,fcol_max'],

            'ptptn' => ['required', 'array'],
            'ptptn.waiver_granted' => ['required', 'boolean'],
            'ptptn.monthly_repayment' => ['required', 'numeric', 'min:0'],
            'ptptn.repayment_start_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],

            'bnpl' => ['required', 'array'],
            'bnpl.*.month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'bnpl.*.amount' => ['required', 'numeric', 'min:0'],
            'bnpl.*.note' => ['nullable', 'string', 'max:200'],

            'events' => ['required', 'array'],
            'events.*.month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'events.*.type' => ['required', 'in:allowance,household,one_off_income,one_off_expense,elr_override'],
            'events.*.amount' => ['required', 'numeric', 'min:0'],
            'events.*.note' => ['nullable', 'string', 'max:200'],

            'elr' => ['required', 'array'],
            'elr.schedules' => ['nullable', 'array'],
            'elr.schedules.*.start_month' => ['required_with:elr.schedules', 'regex:/^\d{4}-\d{2}$/'],
            'elr.schedules.*.end_month' => ['required_with:elr.schedules', 'regex:/^\d{4}-\d{2}$/'],
            'elr.schedules.*.amount' => ['required_with:elr.schedules', 'numeric', 'min:0'],

            'epf' => ['required', 'array'],
            'epf.employee_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'epf.employer_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
