self.addEventListener('message', (event) => {
    const sourceUrl = event.source?.url;
    if (event.origin !== self.location.origin || typeof sourceUrl !== 'string') return;

    const source = new URL(sourceUrl);
    if (source.origin !== self.location.origin || source.pathname !== '/counter') return;
    if (event.data?.type !== 'CLOSE_COUNTER_NOTIFICATION') return;

    event.waitUntil(self.registration.getNotifications({ tag: 'live-finance-counter' })
        .then((notifications) => notifications.forEach((notification) => notification.close())));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/counter';
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const counterWindow = windows.find((client) => new URL(client.url).pathname === targetUrl);

        if (counterWindow) {
            await counterWindow.focus();
            return;
        }

        await self.clients.openWindow(targetUrl);
    })());
});
