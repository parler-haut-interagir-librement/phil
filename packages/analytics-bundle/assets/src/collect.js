/*
 * What phil/analytics-bundle's collection script sends: a page view — the path, the referrer's origin, and the verdict
 * of phil/bot-detection-bundle —, the events of its closed lists, and on leaving a page how far it was read and for
 * how long. Nothing tells one person from another or ties two sends together. No cookie is set or sent, nothing is
 * kept in local or session storage, and nothing is sent when the browser asks not to be tracked.
 */

/** Whether the browser asks not to be tracked: Do Not Track, or Global Privacy Control. */
export function privacyRequested(navigator) {
    return navigator?.doNotTrack === '1' || navigator?.globalPrivacyControl === true;
}

/**
 * The referrer's origin, without its path, query or fragment: the server reads nothing else, and a path can name a
 * person (a profile, a private document, a search). `null` when there is none.
 */
export function referrerOf(referrer) {
    if (typeof referrer !== 'string' || referrer === '') {
        return null;
    }

    try {
        const url = new URL(referrer);

        return url.protocol === 'http:' || url.protocol === 'https:' ? url.origin : null;
    } catch {
        return null;
    }
}

/**
 * The path of the page shown before this one, for a page reached from the same site; `null` otherwise. The query and
 * the fragment stay behind: they may carry a token.
 */
export function previousPathOf(previous, location) {
    if (typeof previous !== 'string' || previous === '') {
        return null;
    }

    try {
        const url = new URL(previous);

        return url.origin === location.origin ? url.pathname : null;
    } catch {
        return null;
    }
}

/**
 * The page view of the current page. The verdict is the one given, or the one phil/bot-detection-bundle's
 * controller left on the root element, or `unknown`: a missing verdict counts nothing, it is not a robot.
 *
 * The referrer is the document's for the first page a document shows. A page Turbo renders afterwards keeps the
 * document, and with it `document.referrer`, the referrer of the first page: its referrer is the page shown before
 * it, `previous`, so a visitor who came from a search engine and browses five pages counts one visit, not six.
 */
export function pageView(location, document, verdict = '', previous = null, dimensions = {}) {
    const left = document?.documentElement?.dataset?.philBotVerdict;
    const shown = previous ?? document?.referrer;
    const from = previousPathOf(shown, location);

    return {
        path: location.pathname,
        referrer: referrerOf(shown),
        verdict: verdict || left || 'unknown',
        ...(from === null ? {} : { from }),
        ...(Object.keys(dimensions).length === 0 ? {} : { dimensions }),
    };
}

/** An event of the page: what its page view said, less the previous page, and the event's own fields. */
export function eventOf(view, event, fields = {}) {
    const { from: _from, ...page } = view;

    return { ...page, event, ...fields };
}

/**
 * What a click reports, as `[event, fields]` pairs: a name the page declared, and for a link a download of a listed
 * extension or a way out to another host. The host alone leaves the page: the path and the query of a link may carry
 * a token, and a link the application declared is told apart by the reference the page gives it.
 */
export function clickEventsOf(target, location, downloads) {
    const events = [];
    const named = target?.closest?.('[data-phil-analytics-click]');

    if (named) {
        events.push(['click', { name: named.dataset.philAnalyticsClick }]);
    }

    const link = target?.closest?.('a[href]');
    const url = link ? urlOf(link.href) : null;

    if (url === null) {
        return events;
    }

    const extension = extensionOf(url.pathname);

    if (extension !== null && downloads.has(extension)) {
        events.push(['download', { extension }]);
    } else if (url.host !== location.host) {
        const reference = link.dataset.philAnalyticsLink;

        events.push(['outbound', { host: url.hostname, ...(reference ? { link: reference } : {}) }]);
    }

    return events;
}

function urlOf(href) {
    try {
        const url = new URL(href);

        return url.protocol === 'http:' || url.protocol === 'https:' ? url : null;
    } catch {
        return null;
    }
}

function extensionOf(pathname) {
    const match = /\.([a-z0-9]{1,8})$/i.exec(pathname);

    return match ? match[1].toLowerCase() : null;
}

/** How far down the page the bottom of the window is, from 0 to 100. A page that fits the window is read whole. */
export function scrollPercent(window, documentElement) {
    const height = documentElement.scrollHeight;

    return height <= window.innerHeight
        ? 100
        : Math.min(100, Math.round(((window.scrollY + window.innerHeight) / height) * 100));
}

/** Measures one page while it is shown: the deepest band reached, and the seconds it was in front of its reader. */
export class EngagementMeter {
    #deepest = 0;
    #active = 0;
    #since;

    constructor(now, visible) {
        this.#since = visible ? now : null;
    }

    scrolled(percent) {
        this.#deepest = Math.max(this.#deepest, Math.floor(percent / 25) * 25);
    }

    visibility(visible, now) {
        if (visible) {
            this.#since ??= now;

            return;
        }

        this.#close(now);
    }

    report(now) {
        this.#close(now);

        return { scroll: this.#deepest, active: Math.round(this.#active / 1000) };
    }

    #close(now) {
        if (this.#since !== null) {
            this.#active += now - this.#since;
            this.#since = null;
        }
    }
}

/**
 * Sends one page view or event, as plain text so that no preflight is needed: by a `fetch` kept alive — it survives the
 * page being left — without credentials, so no cookie of the collection origin goes with it. A browser without
 * `fetch` sends it by beacon, which always carries the collection origin's cookies. Answers whether it was queued.
 */
export function send(endpoint, view, navigator, fetchFunction = globalThis.fetch) {
    const body = JSON.stringify(view);

    if (typeof fetchFunction === 'function') {
        fetchFunction(endpoint, {
            method: 'POST',
            body,
            keepalive: true,
            credentials: 'omit',
            headers: { 'Content-Type': 'text/plain' },
        }).catch(() => {});

        return true;
    }

    if (typeof navigator?.sendBeacon === 'function') {
        return navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }));
    }

    return false;
}
