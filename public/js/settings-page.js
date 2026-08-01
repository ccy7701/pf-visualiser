(() => {
    const config = window.settingsPageConfig || {};
    const preferenceKey = 'counterNotificationEnabled';
    const notificationTag = 'live-finance-counter';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let promptTemplates = Array.isArray(config.promptTemplates)
        ? config.promptTemplates.map((template) => ({ ...template, id: String(template.id) }))
        : [];
    let selectedPromptTemplateId = promptTemplates[0]?.id || null;

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    function supported() {
        return 'Notification' in window && 'serviceWorker' in navigator;
    }

    function enabled() {
        return supported()
            && localStorage.getItem(preferenceKey) === 'true'
            && Notification.permission === 'granted';
    }

    function render(message = '') {
        const button = document.getElementById('counterNotificationToggle');
        const status = document.getElementById('counterNotificationStatus');

        if (button) {
            button.textContent = enabled() ? 'Disable' : 'Enable';
            button.classList.toggle('btn-outline-secondary', !enabled());
            button.classList.toggle('btn-outline-danger', enabled());
            button.disabled = !supported();
        }

        if (status) {
            status.textContent = message || (supported()
                ? 'Shows the live counter while salary is accruing.'
                : 'Browser notifications are not supported here.');
        }
    }

    async function closeNotification() {
        const registration = await navigator.serviceWorker.getRegistration();
        if (!registration) return;
        const notifications = await registration.getNotifications({ tag: notificationTag });
        notifications.forEach((notification) => notification.close());
    }

    async function toggle() {
        if (!supported()) return render();

        if (enabled()) {
            localStorage.setItem(preferenceKey, 'false');
            await closeNotification();
            return render('Counter notification disabled.');
        }

        const permission = await Notification.requestPermission();
        localStorage.setItem(preferenceKey, permission === 'granted' ? 'true' : 'false');
        render(permission === 'granted'
            ? 'Enabled. The notification appears while the counter is accruing.'
            : 'Notification permission was not granted.');
    }

    function setPromptStatus(message, isError = false) {
        const status = document.getElementById('promptTemplateStatus');
        if (!status) return;

        status.textContent = message;
        status.classList.toggle('is-error', isError);
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

    function setPromptPeriodDates(periodType) {
        const startInput = document.getElementById('promptStartDate');
        const endInput = document.getElementById('promptEndDate');
        if (!startInput || !endInput) return;

        const today = new Date();
        let start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        let end = new Date(start);

        if (periodType === 'weekly') {
            const mondayOffset = (start.getDay() + 6) % 7;
            start.setDate(start.getDate() - mondayOffset);
            end = new Date(start);
            end.setDate(end.getDate() + 6);
        } else if (periodType === 'monthly') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        }

        startInput.value = localDateValue(start);
        endInput.value = localDateValue(end);
    }

    function selectedPromptTemplate() {
        return promptTemplates.find((template) => template.id === selectedPromptTemplateId) || null;
    }

    function renderPromptTemplateSelect() {
        const select = document.getElementById('promptTemplateSelect');
        if (!select) return;

        select.replaceChildren();
        if (!selectedPromptTemplateId) {
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

        if (selectedPromptTemplateId) {
            select.value = selectedPromptTemplateId;
        }
        select.disabled = promptTemplates.length === 0;
    }

    function fillPromptTemplateForm(template, resetDates = true) {
        const name = document.getElementById('promptTemplateName');
        const periodType = document.getElementById('promptTemplatePeriodType');
        const body = document.getElementById('promptTemplateBody');
        const save = document.getElementById('savePromptTemplateBtn');
        const remove = document.getElementById('deletePromptTemplateBtn');
        const generate = document.getElementById('generatePromptBtn');
        if (name) name.value = template?.name || '';
        if (periodType) periodType.value = template?.period_type || 'weekly';
        if (body) body.value = template?.body || '';
        if (save) save.textContent = template ? 'Save template' : 'Create template';
        if (remove) remove.disabled = !template;
        if (generate) generate.disabled = !template;
        if (resetDates) setPromptPeriodDates(template?.period_type || 'weekly');
    }

    function renderPromptTemplates(resetDates = true) {
        renderPromptTemplateSelect();
        fillPromptTemplateForm(selectedPromptTemplate(), resetDates);
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
        const isUpdate = Boolean(selectedPromptTemplateId);
        const url = isUpdate
            ? `${config.promptTemplateBaseUrl}/${encodeURIComponent(selectedPromptTemplateId)}`
            : config.promptTemplateStoreUrl;
        const data = await promptRequest(isUpdate ? 'PUT' : 'POST', url, payload);
        const saved = { ...data.template, id: String(data.template.id) };
        const index = promptTemplates.findIndex((template) => template.id === saved.id);
        if (index >= 0) {
            promptTemplates[index] = saved;
        } else {
            promptTemplates.push(saved);
        }
        selectedPromptTemplateId = saved.id;
        renderPromptTemplates(false);
        setPromptStatus(data.message || 'Prompt template saved.');
    }

    async function deletePromptTemplate() {
        const template = selectedPromptTemplate();
        if (!template) return;
        if (!window.confirm(`Delete “${template.name}”?`)) return;

        const url = `${config.promptTemplateBaseUrl}/${encodeURIComponent(template.id)}`;
        const data = await promptRequest('DELETE', url);
        promptTemplates = promptTemplates.filter((item) => item.id !== template.id);
        selectedPromptTemplateId = promptTemplates[0]?.id || null;
        renderPromptTemplates();
        setPromptStatus(data.message || 'Prompt template deleted.');
    }

    function nullablePromptNumber(id) {
        const value = document.getElementById(id)?.value.trim() || '';
        return value === '' ? null : Number(value);
    }

    async function generatePrompt() {
        if (!selectedPromptTemplateId) {
            throw new Error('Save or select a template before generating a prompt.');
        }

        const payload = {
            template_id: Number(selectedPromptTemplateId),
            start_date: document.getElementById('promptStartDate')?.value || '',
            end_date: document.getElementById('promptEndDate')?.value || '',
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
        setPromptStatus(`Prompt generated for ${data.period?.label || 'the selected period'}.`);
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
        setPromptStatus('Prompt copied to clipboard.');
    }

    function initPromptTemplates() {
        const select = document.getElementById('promptTemplateSelect');
        if (!select) return;

        renderPromptTemplates();
        select.addEventListener('change', (event) => {
            selectedPromptTemplateId = event.target.value || null;
            renderPromptTemplates();
            setPromptStatus('');
        });
        document.getElementById('promptTemplatePeriodType')?.addEventListener('change', (event) => {
            setPromptPeriodDates(event.target.value);
        });
        document.getElementById('newPromptTemplateBtn')?.addEventListener('click', () => {
            selectedPromptTemplateId = null;
            renderPromptTemplates();
            document.getElementById('promptTemplateName')?.focus();
            setPromptStatus('Enter the new template details.');
        });
        document.getElementById('savePromptTemplateBtn')?.addEventListener('click', () => {
            savePromptTemplate().catch((error) => setPromptStatus(error.message, true));
        });
        document.getElementById('deletePromptTemplateBtn')?.addEventListener('click', () => {
            deletePromptTemplate().catch((error) => setPromptStatus(error.message, true));
        });
        document.getElementById('generatePromptBtn')?.addEventListener('click', () => {
            generatePrompt().catch((error) => setPromptStatus(error.message, true));
        });
        document.getElementById('copyPromptBtn')?.addEventListener('click', () => {
            copyPrompt().catch(() => setPromptStatus('Unable to copy the prompt.', true));
        });
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('#counterNotificationToggle')) {
            toggle().catch(() => render('Unable to update the counter notification.'));
        }
    });

    const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
    applyTheme(storedTheme);
    render();
    initPromptTemplates();

    window.addEventListener('theme-changed', (event) => applyTheme(event.detail.theme));
})();
