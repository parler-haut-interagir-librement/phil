// What the writer is typing after `@` in a plain text field, and the text once a member is chosen. A `@` counts only
// where a word does not go on before it, as on the server: `ada@example.com` opens nothing.

const TYPED = /@([\p{L}\p{N}_.-]{0,64})$/u;

const BEFORE = /[\p{L}\p{N}_@\\/]/u;

const REST = /^[\p{L}\p{N}_.-]*/u;

/**
 * The handle being typed before the caret, `{query, start, end}` — `start` the offset of the `@` —, or null.
 */
export function activeQuery(text, caret) {
    const from = Math.max(0, caret - 65);
    const match = TYPED.exec(text.slice(from, caret));

    if (null === match) {
        return null;
    }

    const start = from + match.index;

    if (start > 0 && BEFORE.test(text[start - 1])) {
        return null;
    }

    return { query: match[1], start, end: caret };
}

/**
 * The text with the handle being typed, and the rest of its word, replaced by the chosen handle and a space; the caret
 * goes after the space.
 */
export function insertMention(text, range, handle) {
    const insertion = `@${handle} `;
    const after = text.slice(range.end).replace(REST, '').replace(/^ /, '');

    return { text: text.slice(0, range.start) + insertion + after, caret: range.start + insertion.length };
}

/**
 * The handle of a suggestion of the editor's member search, its hint without the `@`; null for a suggestion that
 * names no handle.
 */
export function handleOf(item) {
    const hint = 'string' === typeof item?.hint ? item.hint : '';

    return /^@[^\s@]{1,64}$/u.test(hint) ? hint.slice(1) : null;
}
