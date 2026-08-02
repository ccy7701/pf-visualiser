<?php

namespace App\Http\Controllers;

use App\Models\PromptTemplate;
use App\Services\PromptComposerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromptTemplateController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $template = PromptTemplate::query()->create($this->validatedTemplate($request));

        return response()->json([
            'message' => 'Prompt template created.',
            'template' => $this->templatePayload($template),
        ], 201);
    }

    public function update(Request $request, PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->update($this->validatedTemplate($request));

        return response()->json([
            'message' => 'Prompt template updated.',
            'template' => $this->templatePayload($promptTemplate->fresh()),
        ]);
    }

    public function destroy(PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->delete();

        return response()->json([
            'message' => 'Prompt template deleted.',
        ]);
    }

    public function compose(Request $request, PromptComposerService $composer): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:prompt_templates,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'period_status' => ['nullable', Rule::in(['automatic', 'ongoing', 'complete'])],
            'closing_coh' => ['nullable', 'numeric'],
            'closing_elr' => ['nullable', 'numeric', 'min:0'],
            'closing_epf' => ['nullable', 'numeric', 'min:0'],
            'additional_context' => ['nullable', 'string', 'max:20000'],
            'questions' => ['nullable', 'string', 'max:20000'],
        ]);
        $template = PromptTemplate::query()->findOrFail($validated['template_id']);

        return response()->json($composer->compose($template, $validated));
    }

    private function validatedTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'period_type' => ['required', Rule::in(['weekly', 'monthly', 'custom'])],
            'body' => ['required', 'string', 'max:50000'],
        ]);
    }

    private function templatePayload(PromptTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'period_type' => $template->period_type,
            'body' => $template->body,
        ];
    }
}
