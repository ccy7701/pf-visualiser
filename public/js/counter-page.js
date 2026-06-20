(() => {
    const config = window.counterPageConfig || {};
    const snapshot = config.snapshot || {};

    /* DOM refs */
    const actualCounterElement = document.getElementById('actualCounterValue');
    const expectedCounterElement = document.getElementById('expectedCounterValue');
    /* state */
    let actualValue = Number(snapshot.actual_counter ?? snapshot.counter ?? 0);
    let expectedValue = Number(snapshot.expected_counter ?? snapshot.counter ?? 0);
    let accruedSalaryValue = Number(snapshot.accrued_salary || 0);
    let incrementPerSecond = Number(snapshot.increment_per_second || 0);
    let notificationRegistration = null;

    const notificationPreferenceKey = 'counterNotificationEnabled';
    const notificationTag = 'live-finance-counter';

    const formatter = new Intl.NumberFormat('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function renderCounter() {
        if (actualCounterElement) {
            actualCounterElement.textContent = `RM ${formatter.format(actualValue)}`;
        }

        if (expectedCounterElement) {
            expectedCounterElement.textContent = `RM ${formatter.format(expectedValue)}`;
        }
    }

    function renderAccruedSalary() {
        const el = document.getElementById('accruedSalarySummary');
        if (el) {
            el.textContent = `RM ${formatter.format(accruedSalaryValue)}`;
        }
    }

    function renderDynamicTotal() {
        const el = document.getElementById('dynamicTotalSummary');
        if (el) {
            el.textContent = `RM ${formatter.format(expectedValue)}`;
        }
    }

    function updateIncrementStatus() {
        const statusEl = document.getElementById('incrementStatus');
        if (!statusEl) {
            return;
        }

        if (incrementPerSecond > 0) {
            statusEl.textContent = 'INCREMENTING (GET TO WORK!)';
            statusEl.style.color = '#6c757d';
        } else {
            statusEl.textContent = 'PAUSED INCREMENT (RELAX!)';
            statusEl.style.color = '#6c757d';
        }
    }

    function tick() {
        expectedValue += incrementPerSecond;
        accruedSalaryValue += incrementPerSecond;
        renderCounter();
        renderAccruedSalary();
        renderDynamicTotal();
        updateIncrementStatus();
    }

    async function syncSnapshot() {
        if (!config.snapshotUrl) {
            return;
        }

        const response = await fetch(config.snapshotUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        actualValue = Number(data.actual_counter ?? data.counter ?? 0);
        expectedValue = Number(data.expected_counter ?? data.counter ?? 0);
        accruedSalaryValue = Number(data.accrued_salary);
        incrementPerSecond = Number(data.increment_per_second);
        renderCounter();
        renderAccruedSalary();
        renderDynamicTotal();
        updateIncrementStatus();
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    function notificationsSupported() {
        return 'Notification' in window && 'serviceWorker' in navigator;
    }

    function notificationsEnabled() {
        return localStorage.getItem(notificationPreferenceKey) === 'true';
    }

    function renderNotificationControl(message = '') {
        const button = document.getElementById('counterNotificationToggle');
        const status = document.getElementById('counterNotificationStatus');
        const enabled = notificationsSupported()
            && notificationsEnabled()
            && Notification.permission === 'granted';

        if (button) {
            button.textContent = enabled ? 'Disable' : 'Enable';
            button.classList.toggle('btn-outline-secondary', !enabled);
            button.classList.toggle('btn-outline-danger', enabled);
            button.disabled = !notificationsSupported();
        }

        if (status) {
            status.textContent = message || (notificationsSupported()
                ? 'Refreshes every 60 seconds while this page is open.'
                : 'Browser notifications are not supported here.');
        }
    }

    async function getNotificationRegistration() {
        if (!notificationRegistration) {
            notificationRegistration = await navigator.serviceWorker.register('/counter-notification-sw.js');
        }

        return notificationRegistration;
    }

    async function closeCounterNotification() {
        if (!notificationsSupported()) return;

        const registration = notificationRegistration || await navigator.serviceWorker.getRegistration();
        if (!registration) return;

        const notifications = await registration.getNotifications({ tag: notificationTag });
        notifications.forEach((notification) => notification.close());
    }

    async function updateCounterNotification() {
        if (!notificationsSupported() || !notificationsEnabled() || Notification.permission !== 'granted') return;

        if (incrementPerSecond <= 0) {
            await closeCounterNotification();
            return;
        }

        const registration = await getNotificationRegistration();
        await registration.showNotification(
            `RM${formatter.format(expectedValue)} | Incrementing (GET TO WORK!)`,
            {
                tag: notificationTag,
                renotify: false,
                silent: true,
                requireInteraction: true,
                icon: '/favicon.png',
                data: { url: '/counter' },
            },
        );
    }

    async function toggleCounterNotification() {
        if (!notificationsSupported()) {
            renderNotificationControl('Browser notifications are not supported here.');
            return;
        }

        if (notificationsEnabled() && Notification.permission === 'granted') {
            localStorage.setItem(notificationPreferenceKey, 'false');
            await closeCounterNotification();
            renderNotificationControl('Counter notification disabled.');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            localStorage.setItem(notificationPreferenceKey, 'false');
            renderNotificationControl('Notification permission was not granted.');
            return;
        }

        localStorage.setItem(notificationPreferenceKey, 'true');
        await updateCounterNotification();
        renderNotificationControl(incrementPerSecond > 0
            ? 'Counter notification enabled.'
            : 'Enabled; the notification will appear when incrementing resumes.');
    }

    async function initializeCounterNotification() {
        renderNotificationControl();

        if (!notificationsSupported() || !notificationsEnabled()) return;
        if (Notification.permission !== 'granted') {
            localStorage.setItem(notificationPreferenceKey, 'false');
            renderNotificationControl();
            return;
        }

        await updateCounterNotification();
    }

    /* event listeners */
    document.addEventListener('click', (event) => {
        if (event.target.closest('#counterNotificationToggle')) {
            toggleCounterNotification().catch(() => {
                renderNotificationControl('Unable to update the counter notification.');
            });
        }
    });

    const toastEl = document.getElementById('flashToast');
    if (toastEl) {
        setTimeout(() => {
            toastEl.style.transition = 'opacity 0.4s';
            toastEl.style.opacity = '0';
            setTimeout(() => toastEl.remove(), 400);
        }, 3500);
    }

    renderCounter();
    renderAccruedSalary();
    renderDynamicTotal();
    updateIncrementStatus();
    setInterval(tick, 1000);
    setInterval(syncSnapshot, 60000);
    setInterval(() => {
        updateCounterNotification().catch(() => {});
    }, 60000);
    initializeCounterNotification().catch(() => {
        renderNotificationControl('Unable to initialize the counter notification.');
    });

    window.addEventListener('counter-updated', () => {
        syncSnapshot();
        renderNotificationControl();
    });

    const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
    applyTheme(storedTheme);

    window.addEventListener('theme-changed', (e) => {
        applyTheme(e.detail.theme);
    });

    window.addEventListener('pagehide', () => {
        notificationRegistration?.active?.postMessage({ type: 'CLOSE_COUNTER_NOTIFICATION' });
    });
})();
