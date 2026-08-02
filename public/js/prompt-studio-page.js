(() => {
    const config = window.promptStudioConfig || {};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let promptTemplates = Array.isArray(config.promptTemplates)
        ? config.promptTemplates.map((template) => ({ ...template, id: String(template.id) }))
        : [];
    let editingPromptTemplateId = promptTemplates[0]?.id || null;
    let composingPromptTemplateId = promptTemplates[0]?.id || null;

    function setPromptStatus(message, isError = false, workspace = 'template') {
        const prefix = workspace === 'composer' ? 'promptComposer' : 'promptTemplate';
        const toastElement = document.getElementById(`${prefix}StatusToast`);
        const status = document.getElementById(`${prefix}Status`);
        const icon = document.getElementById(`${prefix}StatusIcon`);
        if (!toastElement || !status) return;

        status.textContent = message;
        toastElement.classList.toggle('is-error', isError);
        if (icon) {
            icon.className = isError
                ? 'fa-solid fa-circle-exclamation text-danger me-2'
                : 'fa-solid fa-circle-check text-success me-2';
        }

        const Toast = window.bootstrap?.Toast;
        if (!message) {
            Toast?.getInstance(toastElement)?.hide();
            return;
        }

        if (Toast) Toast.getOrCreateInstance(toastElement).show();
        else toastElement.classList.add('show');
    }

    async function promptRequest(method, url, payload) {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: payload === undefined ? undefined : JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(validationMessage || data.message || 'Unable to complete the request.');
        }

        return data;
    }

    function localDateValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function isoWeekValue(date) {
        const thursday = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        thursday.setDate(thursday.getDate() + 3 - ((thursday.getDay() + 6) % 7));
        const firstThursday = new Date(thursday.getFullYear(), 0, 4);
        firstThursday.setDate(firstThursday.getDate() + 3 - ((firstThursday.getDay() + 6) % 7));
        const week = 1 + Math.round((thursday - firstThursday) / 604800000);

        return `${thursday.getFullYear()}-W${String(week).padStart(2, '0')}`;
    }

    function isoWeekStart(value) {
        const match = String(value).match(/^(\d{4})-W(\d{2})$/);
        if (!match) return null;

        const januaryFourth = new Date(Number(match[1]), 0, 4);
        const mondayOffset = (januaryFourth.getDay() + 6) % 7;
        januaryFourth.setDate(januaryFourth.getDate() - mondayOffset + ((Number(match[2]) - 1) * 7));

        return januaryFourth;
    }

    function resolvedPeriodLabel(start, end) {
        const formatter = new Intl.DateTimeFormat('en-MY', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });

        return `${formatter.format(start)} – ${formatter.format(end)}`;
    }

    function setPromptPeriod(periodType, resetSelection = false) {
        const startInput = document.getElementById('promptStartDate');
        const endInput = document.getElementById('promptEndDate');
        const weekInput = document.getElementById('promptWeek');
        const monthInput = document.getElementById('promptMonth');
        const weeklyControl = document.getElementById('promptWeeklyPeriodControl');
        const monthlyControl = document.getElementById('promptMonthlyPeriodControl');
        const customControl = document.getElementById('promptCustomPeriodControl');
        const resolvedPeriod = document.getElementById('promptResolvedPeriod');
        if (!startInput || !endInput || !weekInput || !monthInput) return;

        const today = new Date();
        let start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        let end = new Date(start);

        weeklyControl?.classList.toggle('d-none', periodType !== 'weekly');
        monthlyControl?.classList.toggle('d-none', periodType !== 'monthly');
        customControl?.classList.toggle('d-none', periodType !== 'custom');

        if (periodType === 'weekly') {
            if (resetSelection || !weekInput.value) weekInput.value = isoWeekValue(today);
            start = isoWeekStart(weekInput.value) || start;
            end = new Date(start);
            end.setDate(end.getDate() + 6);
        } else if (periodType === 'monthly') {
            if (resetSelection || !monthInput.value) {
                monthInput.value = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
            }
            const [year, month] = monthInput.value.split('-').map(Number);
            start = new Date(year, month - 1, 1);
            end = new Date(year, month, 0);
        } else {
            if (resetSelection || !startInput.value || !endInput.value) {
                startInput.value = localDateValue(today);
                endInput.value = localDateValue(today);
            }
            start = new Date(`${startInput.value}T00:00:00`);
            end = new Date(`${endInput.value}T00:00:00`);
        }

        startInput.value = localDateValue(start);
        endInput.value = localDateValue(end);
        if (resolvedPeriod) resolvedPeriod.textContent = `Date range: ${resolvedPeriodLabel(start, end)}`;
    }

    function composingPromptTemplate() {
        return promptTemplates.find((template) => template.id === composingPromptTemplateId) || null;
    }

    function editingPromptTemplate() {
        return promptTemplates.find((template) => template.id === editingPromptTemplateId) || null;
    }

    function renderPromptTemplateSelect() {
        const select = document.getElementById('promptTemplateSelect');
        if (!select) return;

        select.replaceChildren();
        if (!editingPromptTemplateId) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'New unsaved template';
            select.appendChild(placeholder);
        }
        promptTemplates.forEach((template) => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.name;
            select.appendChild(option);
        });

        if (editingPromptTemplateId) select.value = editingPromptTemplateId;
        select.disabled = promptTemplates.length === 0;
    }

    function renderPromptComposerTemplateSelect() {
        const select = document.getElementById('promptComposerTemplateSelect');
        const generate = document.getElementById('generatePromptBtn');
        if (!select) return;

        select.replaceChildren();
        promptTemplates.forEach((template) => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.name;
            select.appendChild(option);
        });

        if (composingPromptTemplateId) select.value = composingPromptTemplateId;
        select.disabled = promptTemplates.length === 0;
        if (generate) generate.disabled = !composingPromptTemplateId;
    }

    function fillPromptTemplateForm(template) {
        const name = document.getElementById('promptTemplateName');
        const periodType = document.getElementById('promptTemplatePeriodType');
        const body = document.getElementById('promptTemplateBody');
        const save = document.getElementById('savePromptTemplateBtn');
        const remove = document.getElementById('deletePromptTemplateBtn');
        if (name) name.value = template?.name || '';
        if (periodType) periodType.value = template?.period_type || 'weekly';
        if (body) body.value = template?.body || '';
        if (save) save.textContent = template ? 'Save template' : 'Create template';
        if (remove) remove.disabled = !template;
    }

    function renderPromptTemplates() {
        renderPromptTemplateSelect();
        renderPromptComposerTemplateSelect();
        fillPromptTemplateForm(editingPromptTemplate());
    }

    function promptTemplatePayload() {
        return {
            name: document.getElementById('promptTemplateName')?.value.trim() || '',
            period_type: document.getElementById('promptTemplatePeriodType')?.value || 'weekly',
            body: document.getElementById('promptTemplateBody')?.value || '',
        };
    }

    async function savePromptTemplate() {
        const payload = promptTemplatePayload();
        const isUpdate = Boolean(editingPromptTemplateId);
        const url = isUpdate
            ? `${config.promptTemplateBaseUrl}/${encodeURIComponent(editingPromptTemplateId)}`
            : config.promptTemplateStoreUrl;
        const data = await promptRequest(isUpdate ? 'PUT' : 'POST', url, payload);
        const saved = { ...data.template, id: String(data.template.id) };
        const index = promptTemplates.findIndex((template) => template.id === saved.id);
        const previousPeriodType = index >= 0 ? promptTemplates[index].period_type : null;
        if (index >= 0) promptTemplates[index] = saved;
        else promptTemplates.push(saved);
        editingPromptTemplateId = saved.id;
        if (!composingPromptTemplateId) {
            composingPromptTemplateId = saved.id;
            setPromptPeriod(saved.period_type, true);
        } else if (composingPromptTemplateId === saved.id && previousPeriodType !== saved.period_type) {
            setPromptPeriod(saved.period_type, true);
        }
        renderPromptTemplates();
        setPromptStatus(data.message || 'Prompt template saved.', false, 'template');
    }

    async function deletePromptTemplate() {
        const template = editingPromptTemplate();
        if (!template) return;
        if (!window.confirm(`Delete “${template.name}”?`)) return;

        const url = `${config.promptTemplateBaseUrl}/${encodeURIComponent(template.id)}`;
        const data = await promptRequest('DELETE', url);
        promptTemplates = promptTemplates.filter((item) => item.id !== template.id);
        editingPromptTemplateId = promptTemplates[0]?.id || null;
        if (composingPromptTemplateId === template.id) {
            composingPromptTemplateId = promptTemplates[0]?.id || null;
            setPromptPeriod(promptTemplates[0]?.period_type || 'custom', true);
        }
        renderPromptTemplates();
        setPromptStatus(data.message || 'Prompt template deleted.', false, 'template');
    }

    function nullablePromptNumber(id) {
        const value = document.getElementById(id)?.value.trim() || '';
        return value === '' ? null : Number(value);
    }

    async function generatePrompt() {
        const template = composingPromptTemplate();
        if (!template) throw new Error('Save or select a template before generating a prompt.');
        setPromptPeriod(template.period_type);

        const payload = {
            template_id: Number(composingPromptTemplateId),
            start_date: document.getElementById('promptStartDate')?.value || '',
            end_date: document.getElementById('promptEndDate')?.value || '',
            period_status: document.getElementById('promptPeriodStatus')?.value || 'automatic',
            closing_coh: nullablePromptNumber('promptClosingCoh'),
            closing_elr: nullablePromptNumber('promptClosingElr'),
            closing_epf: nullablePromptNumber('promptClosingEpf'),
            additional_context: document.getElementById('promptAdditionalContext')?.value || '',
            questions: document.getElementById('promptQuestions')?.value || '',
        };
        const data = await promptRequest('POST', config.promptComposeUrl, payload);
        const preview = document.getElementById('promptPreview');
        const copy = document.getElementById('copyPromptBtn');
        if (preview) preview.value = data.prompt || '';
        if (copy) copy.disabled = !data.prompt;
        setPromptStatus(`Prompt generated for ${data.period?.label || 'the selected period'}.`, false, 'composer');
    }

    async function copyPrompt() {
        const preview = document.getElementById('promptPreview');
        if (!preview?.value) return;

        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(preview.value);
        } else {
            preview.focus();
            preview.select();
            document.execCommand('copy');
        }
        setPromptStatus('Prompt copied to clipboard.', false, 'composer');
    }

    function initPromptStudio() {
        const editorSelect = document.getElementById('promptTemplateSelect');
        const composerSelect = document.getElementById('promptComposerTemplateSelect');
        if (!editorSelect || !composerSelect) return;

        document.querySelectorAll('#promptStudioTabs [data-bs-toggle="tab"]').forEach((tab) => {
            tab.addEventListener('shown.bs.tab', (event) => {
                const title = document.getElementById('promptStudioSectionTitle');
                const subtitle = document.getElementById('promptStudioSectionSubtitle');
                if (title) title.textContent = event.target.dataset.sectionTitle || '';
                if (subtitle) subtitle.textContent = event.target.dataset.sectionSubtitle || '';
            });
        });

        renderPromptTemplates();
        setPromptPeriod(composingPromptTemplate()?.period_type || 'custom', true);
        editorSelect.addEventListener('change', (event) => {
            editingPromptTemplateId = event.target.value || null;
            renderPromptTemplates();
            setPromptStatus('', false, 'template');
        });
        composerSelect.addEventListener('change', (event) => {
            composingPromptTemplateId = event.target.value || null;
            setPromptPeriod(composingPromptTemplate()?.period_type || 'custom', true);
            renderPromptComposerTemplateSelect();
            setPromptStatus('', false, 'composer');
        });
        document.getElementById('promptWeek')?.addEventListener('change', () => setPromptPeriod('weekly'));
        document.getElementById('promptMonth')?.addEventListener('change', () => setPromptPeriod('monthly'));
        document.getElementById('promptStartDate')?.addEventListener('change', () => setPromptPeriod('custom'));
        document.getElementById('promptEndDate')?.addEventListener('change', () => setPromptPeriod('custom'));
        document.getElementById('newPromptTemplateBtn')?.addEventListener('click', () => {
            editingPromptTemplateId = null;
            renderPromptTemplates();
            document.getElementById('promptTemplateName')?.focus();
            setPromptStatus('Enter the new template details.', false, 'template');
        });
        document.getElementById('savePromptTemplateBtn')?.addEventListener('click', () => {
            savePromptTemplate().catch((error) => setPromptStatus(error.message, true, 'template'));
        });
        document.getElementById('deletePromptTemplateBtn')?.addEventListener('click', () => {
            deletePromptTemplate().catch((error) => setPromptStatus(error.message, true, 'template'));
        });
        document.getElementById('generatePromptBtn')?.addEventListener('click', () => {
            generatePrompt().catch((error) => setPromptStatus(error.message, true, 'composer'));
        });
        document.getElementById('copyPromptBtn')?.addEventListener('click', () => {
            copyPrompt().catch(() => setPromptStatus('Unable to copy the prompt.', true, 'composer'));
        });
    }

    initPromptStudio();
})();
