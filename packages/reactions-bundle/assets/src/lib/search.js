/**
 * A text as the search compares it: no case and no accent.
 */
export function normalized(text) {
    return text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase();
}

/**
 * Whether a label holds what was typed.
 */
export function matches(label, query) {
    return normalized(label).includes(normalized(query).trim());
}
