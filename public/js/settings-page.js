(() => {
    const config = window.settingsPageConfig || {};
    const preferenceKey = 'counterNotificationEnabled';
    const notificationTag = 'live-finance-counter';

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

    document.addEventListener('click', (event) => {
        if (event.target.closest('#counterNotificationToggle')) {
            toggle().catch(() => render('Unable to update the counter notification.'));
        }
    });

    const storedTheme = localStorage.getItem('theme') || config.theme || 'light';
    applyTheme(storedTheme);
    render();

    window.addEventListener('theme-changed', (event) => applyTheme(event.detail.theme));
})();
