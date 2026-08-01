<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Settings</title>
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
    <link href="{{ asset('css/settings.css') }}" rel="stylesheet">
    <link href="{{ asset('css/edge-nav.css') }}" rel="stylesheet">
</head>
<body>
@include('components.module-nav', ['current' => 'settings'])

<main class="container-fluid settings-page py-4 px-3 px-lg-5">
    <header class="mb-3">
        <div>
            <h1 class="h3 mb-1">Settings</h1>
            <p class="text-secondary mb-4">Configure the counter, calendars, salary schedules and reusable analysis prompts</p>
        </div>
    </header>

    <div class="row g-3 settings-layout">
        <div class="col-xl-4">
            <div class="card panel-card settings-nav-card">
                <div class="card-header">Configurations</div>
                <div class="card-body">
                    <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
                        <button class="settings-tab active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general-pane" type="button" role="tab" aria-controls="general-pane" aria-selected="true" aria-label="General settings">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i>
                            <span>General</span>
                        </button>
                        <button class="settings-tab" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar-pane" type="button" role="tab" aria-controls="calendar-pane" aria-selected="false" aria-label="Workday calendar">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span>Workday Calendar</span>
                        </button>
                        <button class="settings-tab" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary-pane" type="button" role="tab" aria-controls="salary-pane" aria-selected="false" aria-label="Salary schedules">
                            <i class="fa-solid fa-money-check-dollar" aria-hidden="true"></i>
                            <span>Salary Schedules</span>
                        </button>
                        <button class="settings-tab" id="prompt-templates-tab" data-bs-toggle="tab" data-bs-target="#prompt-templates-pane" type="button" role="tab" aria-controls="prompt-templates-pane" aria-selected="false" aria-label="Prompt templates">
                            <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                            <span>Prompt Templates</span>
                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <section class="card panel-card settings-content-card">
                <div class="card-body settings-content tab-content">
                    <div class="tab-pane fade show active" id="general-pane" role="tabpanel" aria-labelledby="general-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">General</h2>
                            <p class="text-secondary small mb-0">Set the baseline used by your finance counter and manage its browser notification.</p>
                        </div>
                        <livewire:settings-manager />
                    </div>
                    <div class="tab-pane fade" id="calendar-pane" role="tabpanel" aria-labelledby="calendar-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">Workday calendar</h2>
                            <p class="text-secondary small mb-0">Mark working days, absences and holidays used by salary accrual calculations.</p>
                        </div>
                        <livewire:workday-calendar />
                    </div>
                    <div class="tab-pane fade" id="salary-pane" role="tabpanel" aria-labelledby="salary-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">Salary schedules</h2>
                            <p class="text-secondary small mb-0">Maintain salary periods so counter projections use the correct net pay.</p>
                        </div>
                        <livewire:salary-schedule-manager />
                    </div>
                    <div class="tab-pane fade" id="prompt-templates-pane" role="tabpanel" aria-labelledby="prompt-templates-tab" tabindex="0">
                        <div class="settings-section-heading">
                            <h2 class="h5 mb-1">Prompt templates</h2>
                            <p class="text-secondary small mb-0">Prepare financial data for an external LLM. Nothing is sent outside this application.</p>
                        </div>

                        <div class="prompt-template-workspace">
                            <section class="data-card prompt-template-editor" aria-labelledby="promptTemplateEditorTitle">
                                <div class="prompt-template-card-heading">
                                    <div>
                                        <h3 id="promptTemplateEditorTitle" class="h6 mb-1">Template</h3>
                                        <p class="text-secondary small mb-0">Save reusable wording and placeholders.</p>
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
                                <div class="mb-2">
                                    <label for="promptTemplateBody" class="form-label form-label-sm">Template text</label>
                                    <textarea id="promptTemplateBody" class="form-control form-control-sm prompt-template-body" rows="15" maxlength="50000"></textarea>
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
                            </section>

                            <section class="data-card prompt-composer" aria-labelledby="promptComposerTitle">
                                <div class="prompt-template-card-heading">
                                    <div>
                                        <h3 id="promptComposerTitle" class="h6 mb-1">Compose prompt</h3>
                                        <p class="text-secondary small mb-0">Choose the period, add situational context, then generate copy-ready text.</p>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label for="promptStartDate" class="form-label form-label-sm">Start date</label>
                                        <input id="promptStartDate" class="form-control form-control-sm" type="date">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="promptEndDate" class="form-label form-label-sm">End date</label>
                                        <input id="promptEndDate" class="form-control form-control-sm" type="date">
                                    </div>
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

                                <div class="prompt-preview-wrap mt-3">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <label for="promptPreview" class="form-label form-label-sm mb-0">Preview</label>
                                        <button id="copyPromptBtn" class="btn btn-outline-secondary btn-sm" type="button" disabled>
                                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                            Copy
                                        </button>
                                    </div>
                                    <textarea id="promptPreview" class="form-control form-control-sm prompt-preview" rows="18" readonly placeholder="Your generated prompt will appear here."></textarea>
                                </div>
                            </section>
                        </div>
                        <div id="promptTemplateStatus" class="small mt-3" role="status" aria-live="polite"></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<script>
    window.settingsPageConfig = {
        theme: @json($theme),
        promptTemplates: @json($promptTemplates),
        promptTemplateStoreUrl: @json(route('settings.prompt-templates.store')),
        promptTemplateBaseUrl: @json(url('/settings/prompt-templates')),
        promptComposeUrl: @json(route('settings.prompt-templates.compose')),
    };
</script>
<script type="module" src="{{ asset('js/edge-nav.js') }}"></script>
<script type="module" src="{{ asset('js/settings-page.js') }}"></script>
@livewireScripts
</body>
</html>
