// The fields of a form a draft keeps, their values read and written back. A draft keeps each name with the list of its
// values in the order of the form: one for a text field, one per checked box or selected option, none when nothing is
// checked, so that putting it back also unchecks.

/** The attribute that keeps a field out of drafts (`off`) or puts it in (`on`), on the field or around it. */
export const FIELD = 'data-phil-autosave';

/** The attribute naming the controller to mount again around a field once its value is restored. */
export const REMOUNT = 'data-phil-autosave-remount';

/** Controls whose value is no input, or must never be kept: secrets, files, buttons. */
const SKIPPED = new Set(['password', 'file', 'submit', 'button', 'reset', 'image', 'fieldset', 'output']);

/** The `autocomplete` tokens of secrets: payment cards, passwords, one-time codes. */
const SECRET = /(?:^|\s)(?:cc-[a-z-]+|new-password|current-password|one-time-code)(?:\s|$)/;

/** The names of CSRF tokens: `_token`, `form[_token]`, `_csrf_token`. */
const TOKEN = /(?:^|\[)_(?:csrf_)?token\]?$/;

/** What the nearest element marked around a field says: `on`, `off`, or null. */
export function mark(element) {
    return element.closest?.(`[${FIELD}]`)?.getAttribute(FIELD) ?? null;
}

/**
 * Whether a draft keeps a field: named and enabled, not a secret nor a file nor a button nor a CSRF token, not marked
 * `off`; a hidden field only when marked `on`.
 */
export function isKept(element) {
    const type = String(element.type ?? '').toLowerCase();

    if ('string' !== typeof element.name || '' === element.name || true === element.disabled || SKIPPED.has(type)) {
        return false;
    }

    if (TOKEN.test(element.name) || SECRET.test(String(element.autocomplete ?? '').toLowerCase())) {
        return false;
    }

    const marked = mark(element);

    return 'hidden' === type ? 'on' === marked : 'off' !== marked;
}

/** The values of the fields a draft keeps, by name, in an object without a prototype. */
export function collect(form) {
    const fields = Object.create(null);

    for (const element of form.elements) {
        if (!isKept(element)) {
            continue;
        }

        const type = String(element.type ?? '').toLowerCase();
        fields[element.name] ??= [];

        if ('checkbox' === type || 'radio' === type) {
            if (element.checked) {
                fields[element.name].push(element.value);
            }
        } else if ('select-multiple' === type) {
            for (const option of element.options) {
                if (option.selected) {
                    fields[element.name].push(option.value);
                }
            }
        } else {
            fields[element.name].push(String(element.value));
        }
    }

    return fields;
}

/**
 * Writes a draft's values back into the fields it keeps, the text fields of one name in order; the fields it changed.
 * A name the draft does not have is left as it is.
 */
export function apply(form, fields) {
    const next = new Map();
    const changed = [];

    for (const element of form.elements) {
        if (!isKept(element) || !Object.hasOwn(fields, element.name) || !Array.isArray(fields[element.name])) {
            continue;
        }

        const values = fields[element.name];
        const type = String(element.type ?? '').toLowerCase();

        if ('checkbox' === type || 'radio' === type) {
            const checked = values.includes(element.value);

            if (element.checked !== checked) {
                element.checked = checked;
                changed.push(element);
            }
        } else if ('select-multiple' === type) {
            let moved = false;

            for (const option of element.options) {
                const selected = values.includes(option.value);
                moved ||= option.selected !== selected;
                option.selected = selected;
            }

            if (moved) {
                changed.push(element);
            }
        } else {
            const index = next.get(element.name) ?? 0;
            next.set(element.name, index + 1);

            if ('string' === typeof values[index] && element.value !== values[index]) {
                element.value = values[index];
                changed.push(element);
            }
        }
    }

    return changed;
}

/** Whether two sets of values are the same, name by name and value by value. */
export function sameFields(a, b) {
    const names = Object.keys(a);

    if (names.length !== Object.keys(b).length) {
        return false;
    }

    return names.every(
        (name) =>
            Object.hasOwn(b, name) &&
            Array.isArray(a[name]) &&
            Array.isArray(b[name]) &&
            a[name].length === b[name].length &&
            a[name].every((value, index) => value === b[name][index]),
    );
}

/**
 * Mounts again the controllers named around the changed fields, such as a block editor that reads its input when it
 * connects: the identifier is taken out of `data-controller`, then put back in a later task, once Stimulus has seen it
 * go. The elements remounted.
 */
export function remount(changed, later = (step) => globalThis.setTimeout(step, 0)) {
    const hosts = [];

    for (const element of changed) {
        const identifier = element.getAttribute?.(REMOUNT) ?? null;
        const host = null === identifier ? null : element.closest(`[data-controller~="${identifier}"]`);

        if (null === host || hosts.includes(host)) {
            continue;
        }

        const controllers = host.getAttribute('data-controller');
        host.setAttribute(
            'data-controller',
            controllers
                .split(/\s+/)
                .filter((name) => name !== identifier)
                .join(' '),
        );
        later(() => host.setAttribute('data-controller', controllers));
        hosts.push(host);
    }

    return hosts;
}
