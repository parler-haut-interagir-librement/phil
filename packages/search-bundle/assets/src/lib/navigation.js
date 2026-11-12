// What the filters controller decides without the page: which clicks it takes over, which addresses it fetches, and
// the address a form goes to.

/**
 * Whether a click on a link is a plain one, which opens it in place: the main button, no key held, nobody else
 * having handled it.
 */
export function isPlainClick(event) {
    return (
        !event.defaultPrevented &&
        event.button === 0 &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey
    );
}

/**
 * Whether an address is on the page's own origin: the only ones fetched, any other being followed as a link.
 */
export function isSameOrigin(url, location) {
    return url.origin === location.origin;
}

/**
 * The address a `GET` form goes to with its fields: its action, or the page itself, whose query string they replace.
 */
export function formUrl(action, entries, location) {
    const url = new URL(action || location.href, location.href);
    const query = new URLSearchParams();

    for (const [name, value] of entries) {
        if (typeof value === 'string') {
            query.append(name, value);
        }
    }

    url.search = query.toString();
    url.hash = '';

    return url;
}
