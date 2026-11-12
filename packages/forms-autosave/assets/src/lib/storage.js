// The drafts in the browser's local storage, each under `phil-autosave:<key>`, as JSON: `{v, version, savedAt,
// revision, fields}`. Every access is guarded: a private window, blocked storage or a full quota leaves the form
// working, only without a draft. A draft of another structure, expired, or not one at all is removed when read.

export const PREFIX = 'phil-autosave:';

/** The cookie the server sets with the keys of the forms sent and valid, separated by dots. */
export const DONE_COOKIE = 'phil_forms_autosave_done';

/** The characters and lengths of a key, as the server derives them. */
export const KEY = /^[A-Za-z0-9_-]{16,64}$/;

/** How far in the future a draft's time may be, for a clock that moved: one minute. */
const SKEW = 60000;

export function storageKey(key) {
    return PREFIX + key;
}

/** The local storage of the page, or null when the browser refuses it. */
export function browserStorage(scope = globalThis) {
    try {
        return scope.localStorage ?? null;
    } catch {
        return null;
    }
}

/** A draft from its JSON, or null when the text is not one. */
export function parseDraft(json) {
    let draft;

    try {
        draft = JSON.parse(json);
    } catch {
        return null;
    }

    if (
        null === draft ||
        'object' !== typeof draft ||
        1 !== draft.v ||
        'string' !== typeof draft.version ||
        !Number.isFinite(draft.savedAt) ||
        !(null === draft.revision || (Number.isInteger(draft.revision) && draft.revision > 0)) ||
        !isFields(draft.fields)
    ) {
        return null;
    }

    return { version: draft.version, savedAt: draft.savedAt, revision: draft.revision, fields: draft.fields };
}

/** Whether a value is a set of fields: an object of lists of texts. */
export function isFields(fields) {
    return (
        null !== fields &&
        'object' === typeof fields &&
        !Array.isArray(fields) &&
        Object.values(fields).every(
            (values) => Array.isArray(values) && values.every((value) => 'string' === typeof value),
        )
    );
}

/**
 * The draft kept under a key for this structure, saved less than `ttl` seconds before `now`; null otherwise, the
 * draft found then removed.
 */
export function readDraft(storage, key, version, ttl, now) {
    let json = null;

    try {
        json = storage?.getItem(storageKey(key)) ?? null;
    } catch {
        return null;
    }

    if (null === json) {
        return null;
    }

    const draft = parseDraft(json);

    if (null === draft || draft.version !== version || now - draft.savedAt > ttl * 1000 || draft.savedAt > now + SKEW) {
        removeDraft(storage, key);

        return null;
    }

    return draft;
}

/**
 * Keeps a draft: `saved`; `tooLarge` above `maxBytes` once encoded, the older draft then removed; `unavailable` when
 * the browser refuses.
 */
export function writeDraft(storage, key, draft, maxBytes) {
    const json = JSON.stringify({ v: 1, ...draft });

    if (new TextEncoder().encode(json).length > maxBytes) {
        removeDraft(storage, key);

        return 'tooLarge';
    }

    try {
        if (null === storage) {
            return 'unavailable';
        }

        storage.setItem(storageKey(key), json);
    } catch {
        return 'unavailable';
    }

    return 'saved';
}

export function removeDraft(storage, key) {
    try {
        storage?.removeItem(storageKey(key));
    } catch {
        // Nothing to remove from a storage the browser refuses.
    }
}

/** The keys a cookie string lists as done. */
export function doneKeys(cookies) {
    const pair = String(cookies ?? '')
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith(`${DONE_COOKIE}=`));

    if (undefined === pair) {
        return [];
    }

    let value = '';

    try {
        value = decodeURIComponent(pair.slice(DONE_COOKIE.length + 1));
    } catch {
        return [];
    }

    return value.split('.').filter((key) => KEY.test(key));
}

/** Removes the drafts of the forms the server said were sent, then the cookie that said so; the keys forgotten. */
export function forgetDone(storage, doc) {
    const keys = doneKeys(doc.cookie);

    if (0 === keys.length) {
        return keys;
    }

    for (const key of keys) {
        removeDraft(storage, key);
    }

    doc.cookie = `${DONE_COOKIE}=; path=/; max-age=0; samesite=lax`;

    return keys;
}
