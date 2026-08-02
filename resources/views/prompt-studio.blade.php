<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Prompt Studio</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || @json($theme ?? 'light');
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <link href="{{ asset('css/projection.css') }}" rel="stylesheet">
    <link href="{{ asset('css/prompt-studio.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'prompt-studio'])

<main class="container-fluid prompt-studio-page py-4 px-3 px-lg-5">
    <header class="mb-3">
        <h1 class="h3 mb-1">Prompt Studio</h1>
        <p class="text-secondary mb-4">Prepare and compose financial prompts for an external LLM without sending data outside this application</p>
    </header>

    <section class="card panel-card prompt-studio-content-card">
        <div class="card-header prompt-studio-workspace-header">
            <div class="prompt-studio-section-title">
                <h2 id="promptStudioSectionTitle" class="h5 mb-1">Prompt templates</h2>
                <p id="promptStudioSectionSubtitle" class="text-secondary small mb-0">Save reusable wording, period presets and data placeholders.</p>
            </div>
            <nav class="projection-input-tabs prompt-studio-tabs" id="promptStudioTabs" role="tablist" aria-label="Prompt Studio workspaces">
                <button class="projection-input-tab active" id="prompt-templates-tab" data-bs-toggle="tab" data-bs-target="#prompt-templates-pane" data-section-title="Prompt templates" data-section-subtitle="Save reusable wording, period presets and data placeholders." type="button" role="tab" aria-controls="prompt-templates-pane" aria-selected="true" aria-label="Prompt Templates" data-bs-title="Prompt Templates">
                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                </button>
                <button class="projection-input-tab" id="prompt-composer-tab" data-bs-toggle="tab" data-bs-target="#prompt-composer-pane" data-section-title="Prompt composer" data-section-subtitle="Use a saved template to generate copy-ready text." type="button" role="tab" aria-controls="prompt-composer-pane" aria-selected="false" aria-label="Prompt Composer" data-bs-title="Prompt Composer">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                </button>
            </nav>
        </div>

        <div class="card-body prompt-studio-content tab-content">
                    <div class="tab-pane fade show active" id="prompt-templates-pane" role="tabpanel" aria-labelledby="prompt-templates-tab" tabindex="0">
                        <section class="prompt-template-editor" aria-labelledby="promptTemplateEditorTitle">
                            <div class="row g-3 prompt-template-layout">
                                <div class="col-lg-6 d-flex">
                                    <div class="data-card prompt-template-details w-100">
                                        <div class="prompt-card-heading">
                                            <div>
                                                <h3 id="promptTemplateEditorTitle" class="h6 mb-1">Template details</h3>
                                                <p class="text-secondary small mb-0">Create or update a saved template.</p>
                                            </div>
                                            <div class="prompt-template-actions">
                                                <button id="newPromptTemplateBtn" class="btn btn-outline-secondary btn-sm" type="button">New</button>
                                                <button id="deletePromptTemplateBtn" class="btn btn-outline-danger btn-sm" type="button">Delete</button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="promptTemplateSelect" class="form-label form-label-sm">Saved template</label>
                                            <select id="promptTemplateSelect" class="form-select form-select-sm"></select>
                                        </div>
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-8">
                                                <label for="promptTemplateName" class="form-label form-label-sm">Name</label>
                                                <input id="promptTemplateName" class="form-control form-control-sm" type="text" maxlength="120">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="promptTemplatePeriodType" class="form-label form-label-sm">Period preset</label>
                                                <select id="promptTemplatePeriodType" class="form-select form-select-sm">
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly">Monthly</option>
                                                    <option value="custom">Custom</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="prompt-placeholder-help small text-secondary mb-3">
                                            <span>Available placeholders:</span>
                                            <code>@{{period_intro}}</code>
                                            <code>@{{period}}</code>
                                            <code>@{{start_date}}</code>
                                            <code>@{{end_date}}</code>
                                            <code>@{{month}}</code>
                                            <code>@{{positions}}</code>
                                            <code>@{{positions_comparison}}</code>
                                            <code>@{{expense_total}}</code>
                                            <code>@{{expense_breakdown}}</code>
                                            <code>@{{income_total}}</code>
                                            <code>@{{income_breakdown}}</code>
                                            <code>@{{additional_context}}</code>
                                            <code>@{{questions}}</code>
                                        </div>
                                        <button id="savePromptTemplateBtn" class="btn btn-dark btn-sm" type="button">Save template</button>
                                    </div>
                                </div>

                                <div class="col-lg-6 d-flex">
                                    <div class="data-card prompt-template-text-column w-100">
                                        <div class="prompt-card-heading">
                                            <div>
                                                <h3 class="h6 mb-1">Template text</h3>
                                                <p class="text-secondary small mb-0">Write the reusable prompt structure.</p>
                                            </div>
                                        </div>
                                        <label for="promptTemplateBody" class="visually-hidden">Template text</label>
                                        <textarea id="promptTemplateBody" class="form-control form-control-sm prompt-template-body" rows="15" maxlength="50000"></textarea>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="tab-pane fade" id="prompt-composer-pane" role="tabpanel" aria-labelledby="prompt-composer-tab" tabindex="0">
                        <section class="prompt-composer" aria-labelledby="promptComposerTitle">
                            <div class="row g-3 prompt-composer-layout">
                                <div class="col-lg-6 d-flex">
                                    <div class="prompt-composer-column prompt-composer-working w-100">
                                        <div class="prompt-card-heading">
                                            <div>
                                                <h3 id="promptComposerTitle" class="h6 mb-1">Working</h3>
                                                <p class="text-secondary small mb-0">Choose the period, add context, then generate a preview.</p>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="promptComposerTemplateSelect" class="form-label form-label-sm">Template to use</label>
                                            <select id="promptComposerTemplateSelect" class="form-select form-select-sm"></select>
                                        </div>
                                        <div id="promptWeeklyPeriodControl" class="mb-2 d-none">
                                            <label for="promptWeek" class="form-label form-label-sm">Week</label>
                                            <input id="promptWeek" class="form-control form-control-sm" type="week">
                                        </div>
                                        <div id="promptMonthlyPeriodControl" class="mb-2 d-none">
                                            <label for="promptMonth" class="form-label form-label-sm">Month</label>
                                            <input id="promptMonth" class="form-control form-control-sm" type="month">
                                        </div>
                                        <div id="promptCustomPeriodControl" class="row g-2 mb-2 d-none">
                                            <div class="col-md-6">
                                                <label for="promptStartDate" class="form-label form-label-sm">Start date</label>
                                                <input id="promptStartDate" class="form-control form-control-sm" type="date">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="promptEndDate" class="form-label form-label-sm">End date</label>
                                                <input id="promptEndDate" class="form-control form-control-sm" type="date">
                                            </div>
                                        </div>
                                        <div id="promptResolvedPeriod" class="small text-secondary mb-3"></div>
                                        <div class="mb-3">
                                            <label for="promptPeriodStatus" class="form-label form-label-sm">Period status</label>
                                            <select id="promptPeriodStatus" class="form-select form-select-sm">
                                                <option value="automatic">Automatic based on date</option>
                                                <option value="ongoing">Still ongoing</option>
                                                <option value="complete">Complete</option>
                                            </select>
                                        </div>

                                        <fieldset class="prompt-position-overrides mb-3">
                                            <legend class="form-label form-label-sm mb-1">Position overrides (optional)</legend>
                                            <p class="text-secondary small mb-2">Useful for weekly balances that are not historically snapshotted. LFP and TFP are derived automatically.</p>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label for="promptClosingCoh" class="form-label form-label-sm">COH (RM)</label>
                                                    <input id="promptClosingCoh" class="form-control form-control-sm" type="number" step="0.01" placeholder="Automatic">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="promptClosingElr" class="form-label form-label-sm">ELR (RM)</label>
                                                    <input id="promptClosingElr" class="form-control form-control-sm" type="number" min="0" step="0.01" placeholder="Automatic">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="promptClosingEpf" class="form-label form-label-sm">EPF (RM)</label>
                                                    <input id="promptClosingEpf" class="form-control form-control-sm" type="number" min="0" step="0.01" placeholder="Automatic">
                                                </div>
                                            </div>
                                        </fieldset>

                                        <div class="mb-3">
                                            <label for="promptAdditionalContext" class="form-label form-label-sm">Additional context</label>
                                            <textarea id="promptAdditionalContext" class="form-control form-control-sm" rows="5" maxlength="20000" placeholder="Events, commitments, exceptions, prior assumptions…"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="promptQuestions" class="form-label form-label-sm">Questions for the LLM</label>
                                            <textarea id="promptQuestions" class="form-control form-control-sm" rows="5" maxlength="20000" placeholder="How are things looking? How does this compare with the plan?"></textarea>
                                        </div>
                                        <button id="generatePromptBtn" class="btn btn-dark btn-sm" type="button">Generate preview</button>
                                    </div>
                                </div>

                                <div class="col-lg-6 d-flex">
                                    <div class="prompt-composer-column prompt-composer-output w-100">
                                        <div class="prompt-card-heading">
                                            <div>
                                                <h3 class="h6 mb-1">Output</h3>
                                                <p class="text-secondary small mb-0">Review and copy the generated prompt.</p>
                                            </div>
                                            <button id="copyPromptBtn" class="btn btn-outline-secondary btn-sm" type="button" disabled>
                                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                                Copy
                                            </button>
                                        </div>
                                        <label for="promptPreview" class="visually-hidden">Generated prompt preview</label>
                                        <textarea id="promptPreview" class="form-control form-control-sm prompt-preview" readonly placeholder="Your generated prompt will appear here."></textarea>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
        </div>
    </section>
</main>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="promptTemplateStatusToast" class="toast prompt-status-toast" role="status" aria-live="polite" aria-atomic="true" data-bs-delay="4500">
        <div class="toast-header">
            <i id="promptTemplateStatusIcon" class="fa-solid fa-circle-check text-success me-2" aria-hidden="true"></i>
            <strong class="me-auto">Prompt Templates</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div id="promptTemplateStatus" class="toast-body"></div>
    </div>
    <div id="promptComposerStatusToast" class="toast prompt-status-toast" role="status" aria-live="polite" aria-atomic="true" data-bs-delay="4500">
        <div class="toast-header">
            <i id="promptComposerStatusIcon" class="fa-solid fa-circle-check text-success me-2" aria-hidden="true"></i>
            <strong class="me-auto">Prompt Composer</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div id="promptComposerStatus" class="toast-body"></div>
    </div>
</div>

<script>
    window.promptStudioConfig = {
        theme: @json($theme),
        promptTemplates: @json($promptTemplates),
        promptTemplateStoreUrl: @json(route('prompt-studio.templates.store')),
        promptTemplateBaseUrl: @json(url('/prompt-studio/templates')),
        promptComposeUrl: @json(route('prompt-studio.compose')),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/prompt-studio-page.js') }}"></script>
@livewireScripts
</body>
</html>
