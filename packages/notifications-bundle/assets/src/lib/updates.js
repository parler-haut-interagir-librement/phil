// What the controller reads of a Mercure update and builds from it, without a DOM of its own: the update parsed and
// checked, and a toast element whose text is set as text, never as markup.

/**
 * The update of a recipient's topic, `{"unread": n, "toast": {...}}`, or null when it is not one.
 */
export function parseUpdate(data) {
    let update;

    try {
        update = JSON.parse(data);
    } catch {
        return null;
    }

    if (null === update || 'object' !== typeof update || !Number.isInteger(update.unread) || update.unread < 0) {
        return null;
    }

    const toast = update.toast;
    const valid =
        null !== toast && 'object' === typeof toast && 'string' === typeof toast.id && 'string' === typeof toast.title;

    return { unread: update.unread, toast: valid ? toast : null };
}

/**
 * Whether a URL may be the link of a toast: a path of this site, or an http(s) address.
 */
export function safeUrl(url) {
    return 'string' === typeof url && (/^\/(?!\/)/.test(url) || /^https?:\/\//i.test(url));
}

/**
 * The toast element, as the server renders it in `_toast.html.twig`.
 */
export function toastElement(document, toast, dismissLabel) {
    const element = document.createElement('div');
    element.className = 'phil-notifications-toast';
    element.dataset.notificationId = toast.id;

    const title = document.createElement('p');
    title.className = 'phil-notifications-toast__title';

    if (safeUrl(toast.url)) {
        const link = document.createElement('a');
        link.href = toast.url;
        link.textContent = toast.title;
        title.append(link);
    } else {
        title.textContent = toast.title;
    }

    element.append(title);

    if ('string' === typeof toast.body && '' !== toast.body) {
        const body = document.createElement('p');
        body.className = 'phil-notifications-toast__body';
        body.textContent = toast.body;
        element.append(body);
    }

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'phil-notifications-toast__close';
    close.dataset.philNotificationsDismiss = '';
    close.setAttribute('aria-label', dismissLabel);
    close.textContent = '×';
    element.append(close);

    return element;
}

/**
 * How long a toast stays, in milliseconds: longer when motion is reduced, since it then appears without a cue.
 */
export function dismissDelay(reducedMotion) {
    return reducedMotion ? 10000 : 6000;
}
