// The service worker of Web Push: each message the site sends — `{"id", "type", "title", "body", "url", "count"}` — is
// shown as a notification, a later one of the same notification replacing it; a click opens its page, on this site
// only. An application with a service worker of its own loads this one into it with `importScripts()`.
/* eslint-env serviceworker */

function philNotificationsMessage(data) {
    let message;

    try {
        message = data ? data.json() : null;
    } catch {
        return null;
    }

    if (null === message || 'object' !== typeof message || 'string' !== typeof message.title) {
        return null;
    }

    return message;
}

function philNotificationsPage(url, origin) {
    if ('string' !== typeof url || '' === url) {
        return null;
    }

    try {
        const page = new URL(url, origin);

        return page.origin === origin ? page.href : null;
    } catch {
        return null;
    }
}

self.addEventListener('push', (event) => {
    const message = philNotificationsMessage(event.data);

    if (null === message) {
        return;
    }

    event.waitUntil(
        self.registration.showNotification(message.title, {
            body: 'string' === typeof message.body ? message.body : '',
            tag: 'string' === typeof message.id ? message.id : undefined,
            data: { url: philNotificationsPage(message.url, self.location.origin) },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url;

    if ('string' === typeof url) {
        event.waitUntil(self.clients.openWindow(url));
    }
});
