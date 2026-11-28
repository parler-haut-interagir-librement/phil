// The pure parts of the preview controller: the data of a block read from its fields, the request that asks the
// server to draw it, the answer read back, and the delay that waits for the author to pause.

/** The header that tells the server the request comes from the preview controller. */
export const HEADER = 'X-Phil-Svg-Blocks';

/**
 * The data of a block from its fields, each `{name, value, kind}`: a field of kind `json` holds an object whose
 * properties are merged in — the whole configuration of a chart —, any other is a text under its name.
 *
 * @throws {SyntaxError} when a JSON field does not hold an object
 */
export function collect(fields) {
    const data = {};

    for (const { name, value, kind } of fields) {
        if ('json' === kind) {
            const parsed = JSON.parse(value);

            if (null === parsed || 'object' !== typeof parsed || Array.isArray(parsed)) {
                throw new SyntaxError('The configuration must be a JSON object.');
            }

            Object.assign(data, parsed);
        } else if ('string' === typeof name && '' !== name) {
            data[name] = value;
        }
    }

    return data;
}

/** The options of the `fetch()` that asks for the drawing of a block. */
export function requestInit(type, data, headers = {}) {
    return {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', [HEADER]: '1', ...headers },
        body: JSON.stringify({ type, data }),
    };
}

/** What to show for an answer: `{markup}` on success, else `{error}` with the server's reason or a general one. */
export function answer(status, json) {
    if (200 === status && 'string' === typeof json?.markup) {
        return { markup: json.markup };
    }

    return { error: 'string' === typeof json?.error ? json.error : `The preview failed (${status}).` };
}

/**
 * A function that calls `fn` once, `delay` milliseconds after its last call; `cancel()` drops a pending call.
 */
export function debounce(fn, delay, clock = globalThis) {
    let timer = null;
    const debounced = () => {
        clock.clearTimeout(timer);
        timer = clock.setTimeout(() => {
            timer = null;
            fn();
        }, delay);
    };
    debounced.cancel = () => {
        clock.clearTimeout(timer);
        timer = null;
    };

    return debounced;
}
