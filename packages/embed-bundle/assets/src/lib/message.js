// The frame protocol of phil/embed-bundle, read by the same rules as its reference, the bundle's EmbedMessage class:
// an object with protocol "phil-embed", version 1 and a type; "resize" adds a whole height from 0 to 100 000, "theme"
// adds a theme's name; nothing else.

export const PROTOCOL = 'phil-embed';
export const VERSION = 1;
export const MAX_HEIGHT = 100000;

const THEME = /^[a-z][a-z0-9-]{0,31}$/;
const FIELDS = {
    ready: ['protocol', 'type', 'version'],
    resize: ['height', 'protocol', 'type', 'version'],
    theme: ['protocol', 'theme', 'type', 'version'],
};

/**
 * The message, as {type, height?, theme?}, or null when the data is not one of the protocol.
 *
 * @param {unknown} data
 * @returns {{type: 'ready'}|{type: 'resize', height: number}|{type: 'theme', theme: string}|null}
 */
export function parseMessage(data) {
    if (
        data === null ||
        typeof data !== 'object' ||
        Array.isArray(data) ||
        Object.getPrototypeOf(data) !== Object.prototype
    ) {
        return null;
    }

    if (data.protocol !== PROTOCOL || data.version !== VERSION || !Object.hasOwn(FIELDS, data.type)) {
        return null;
    }

    const keys = Object.keys(data).sort();

    if (keys.join(',') !== FIELDS[data.type].join(',')) {
        return null;
    }

    if (data.type === 'resize') {
        return Number.isInteger(data.height) && data.height >= 0 && data.height <= MAX_HEIGHT
            ? { type: 'resize', height: data.height }
            : null;
    }

    if (data.type === 'theme') {
        return typeof data.theme === 'string' && THEME.test(data.theme) ? { type: 'theme', theme: data.theme } : null;
    }

    return { type: 'ready' };
}

/**
 * The data of a message to post.
 *
 * @param {'ready'|'resize'|'theme'} type
 * @param {{height?: number, theme?: string}} [values]
 */
export function message(type, values = {}) {
    return { protocol: PROTOCOL, version: VERSION, type, ...values };
}
