// The editor's menus as data: the entries found by what was typed, the selection moved with the arrows, the trigger
// of the slash, mention and page-link menus found before the caret, and the HTML a chosen suggestion inserts. Pure
// functions, without the DOM.

import { escapeHtml, isSafeUrl } from './inline.js';

/** A text folded for matching: lower case, without diacritics. */
export function fold(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
}

/**
 * The entries matching a query, the best first: labels that start with it, then words that start with it, then labels
 * and words that hold it. Each entry has a `label` and `keywords`; an empty query keeps every entry in order.
 */
export function filterItems(items, query) {
    const wanted = fold(query.trim());

    if ('' === wanted) {
        return items;
    }

    const rank = (item) => {
        const label = fold(item.label);
        const words = (item.keywords ?? []).map(fold);

        if (label.startsWith(wanted) || label.split(/\s+/).some((word) => word.startsWith(wanted))) {
            return 0;
        }

        if (words.some((word) => word.startsWith(wanted))) {
            return 1;
        }

        return label.includes(wanted) || words.some((word) => word.includes(wanted)) ? 2 : 3;
    };

    return items
        .map((item, index) => ({ item, index, rank: rank(item) }))
        .filter((entry) => entry.rank < 3)
        .sort((a, b) => a.rank - b.rank || a.index - b.index)
        .map((entry) => entry.item);
}

/** The index after a move of `delta` in a list of `length` entries, wrapping around; -1 for an empty list. */
export function moveIndex(index, delta, length) {
    return 0 === length ? -1 : (((index + delta) % length) + length) % length;
}

/**
 * The menu the text before the caret opens, or null: `/` at the start or after a space opens the slash menu, `@`
 * the mention menu, `[[` the page-link menu, each with the query typed after it and the length to replace when an
 * entry is chosen. `enabled` names the menus offered.
 */
export function findTrigger(before, enabled = ['slash', 'mention', 'link']) {
    const patterns = [
        ['link', /\[\[([^\]\n[]{0,60})$/],
        ['mention', /(?:^|[\s( ])@([^\s@]{0,40})$/],
        ['slash', /(?:^|[\s ])\/([^\s/]{0,24})$/],
    ];

    for (const [kind, pattern] of patterns) {
        const found = enabled.includes(kind) ? pattern.exec(before) : null;

        if (null !== found) {
            return { kind, query: found[1], length: found[1].length + ('link' === kind ? 2 : 1) };
        }
    }

    return null;
}

/**
 * The inline HTML of a chosen suggestion: a mention `<a data-mention>` shown as `@label`, or a page link
 * `<a data-ref>` shown as its label, with its address when it is safe.
 */
export function referenceHtml(kind, suggestion) {
    const attribute = 'mention' === kind ? 'data-mention' : 'data-ref';
    const href =
        'string' === typeof suggestion.url && isSafeUrl(suggestion.url)
            ? ' href="' + escapeHtml(suggestion.url) + '"'
            : '';

    return (
        '<a' +
        href +
        ' ' +
        attribute +
        '="' +
        escapeHtml(suggestion.reference) +
        '">' +
        escapeHtml(('mention' === kind ? '@' : '') + suggestion.label) +
        '</a>'
    );
}

/** The address of a search, the query in `q`, keeping what the address already asks. */
export function searchUrl(base, query) {
    return base + (base.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(query);
}

/** The suggestions of a search's answer that the editor can insert. */
export function suggestions(answer) {
    const items = Array.isArray(answer?.items) ? answer.items : [];

    return items.filter(
        (item) =>
            'string' === typeof item?.reference &&
            /^[a-z][a-z0-9_-]{0,31}:[A-Za-z0-9_./-]{1,128}$/.test(item.reference) &&
            'string' === typeof item.label &&
            '' !== item.label,
    );
}
