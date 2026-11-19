/*
 * The report phil/error-tracking-bundle's controller sends for an uncaught error: its type, its message, its stack and
 * the page it happened on — the page's origin and path, never its query nor its fragment, which carry search terms,
 * tokens and invitations. The server bounds and scrubs it again; the bounds here keep the request small.
 */

export const MAX_MESSAGE = 1024;
export const MAX_STACK = 8192;

/** The page an error happened on, without its query nor its fragment. */
export function pageOf(location) {
    return `${location.origin}${location.pathname}`;
}

/**
 * The report of what an `error` or `unhandledrejection` event carries: an `Error`, or anything a promise was rejected
 * with. `null` when there is nothing to report, such as an error of a script of another origin, which the browser
 * reduces to "Script error." without stack.
 */
export function reportOf(thrown, fallbackMessage, location) {
    const isError = thrown instanceof Error;
    const message = String(isError ? thrown.message : (thrown ?? fallbackMessage ?? '')).slice(0, MAX_MESSAGE);

    if (message === '' || (!isError && message === 'Script error.')) {
        return null;
    }

    return {
        type: isError && typeof thrown.name === 'string' ? thrown.name : 'Error',
        message,
        url: pageOf(location),
        stack: isError && typeof thrown.stack === 'string' ? thrown.stack.slice(0, MAX_STACK) : '',
    };
}

/**
 * Sends a report once, as JSON by a `fetch` kept alive — it survives the page being left — without credentials, with
 * the token when there is one. Answers whether it was queued.
 */
export function send(endpoint, report, token, fetchFunction = globalThis.fetch) {
    if (typeof fetchFunction !== 'function') {
        return false;
    }

    const headers = { 'Content-Type': 'application/json' };

    if (token) {
        headers['X-Error-Tracking-Token'] = token;
    }

    fetchFunction(endpoint, {
        method: 'POST',
        body: JSON.stringify(report),
        headers,
        keepalive: true,
        credentials: 'omit',
    }).catch(() => {});

    return true;
}
