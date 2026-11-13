// Passages of the block editor's fields, counted as the server counts them: offsets are characters (Unicode code
// points) of a field's text, its markup left out. These functions mirror phil/comments-bundle's PHP: the text of
// inline HTML, a passage found again by its exact text and its context, and a passage replaced with its tags kept.

export const CONTEXT = 32;

const FIELDS = '.pbe-rich[data-field], .pbe-rich[data-item-field], .pbe-rich[data-cell]';

const UNITS = /<[^>]*>|&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});|[\s\S]/gu;

const NAMED = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

const PLAIN = { code: 'code', mermaid: 'code', math: 'latex' };

/** The number of characters of a text, as PHP's `mb_strlen()` counts them. */
export function length(text) {
    return Array.from(text).length;
}

/** The characters from `start` to `end`. */
export function slice(text, start, end = undefined) {
    return Array.from(text).slice(start, end).join('');
}

/** The text a character reference stands for; a name the editor never writes stays as written. */
function decode(reference) {
    const body = reference.slice(1, -1);

    if ('#' !== body[0]) {
        return NAMED[body] ?? reference;
    }

    const code =
        'x' === body[1] || 'X' === body[1] ? Number.parseInt(body.slice(2), 16) : Number.parseInt(body.slice(1), 10);

    return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : '\ufffd';
}

/** Inline HTML cut into tags, whose text is null, character references and single characters. */
function units(html) {
    return Array.from(String(html).matchAll(UNITS), ([unit]) => {
        if ('<' === unit[0] && unit.length > 1) {
            return [unit, null];
        }

        return [unit, '&' === unit[0] && unit.length > 1 ? decode(unit) : unit];
    });
}

/** The text of inline HTML, as `textContent` reads it. */
export function htmlText(html) {
    return units(html)
        .map(([, text]) => text ?? '')
        .join('');
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

/** The HTML with `length` characters of its text from `start` replaced, every tag kept where it was. */
export function replaceHtml(html, start, count, replacement) {
    let out = '';
    let index = 0;
    let written = false;

    for (const [unit, text] of units(html)) {
        if (null === text || index < start || index >= start + count) {
            out += unit;
        } else if (!written) {
            out += escapeHtml(replacement);
            written = true;
        }

        index += length(text ?? '');
    }

    return out;
}

/** How many characters two contexts share, from their end or from their start. */
function shared(kept, found, fromEnd) {
    const a = Array.from(kept);
    const b = Array.from(found);

    if (fromEnd) {
        a.reverse();
        b.reverse();
    }

    let count = 0;

    while (count < a.length && count < b.length && a[count] === b[count]) {
        count += 1;
    }

    return count;
}

function* occurrences(text, quote) {
    const characters = Array.from(text);
    const size = length(quote);
    let counted = 0;
    let offset = 0;
    let found = text.indexOf(quote);

    while (found >= 0) {
        offset += length(text.slice(counted, found));
        counted = found;
        yield [
            offset,
            characters.slice(Math.max(0, offset - CONTEXT), offset).join(''),
            characters.slice(offset + size, offset + size + CONTEXT).join(''),
        ];
        found = text.indexOf(quote, found + 1);
    }
}

function better(a, b) {
    for (let i = 0; i < a.length; i += 1) {
        if (a[i] !== b[i]) {
            return a[i] > b[i];
        }
    }

    return false;
}

/**
 * The anchor of a passage in texts `[{block, field, text}]` given in reading order, or null when it occurs in none:
 * its own field first, then its block, then the rest; then the context kept with it; then, in its field, the place
 * nearest its old offset, elsewhere the first.
 */
export function reanchor(anchor, passages) {
    let best = null;
    let rank = null;

    passages.forEach((passage, order) => {
        const same = passage.block === anchor.block && passage.field === anchor.field;
        const tier = same ? 2 : null !== anchor.block && passage.block === anchor.block ? 1 : 0;

        for (const [offset, before, after] of occurrences(passage.text, anchor.quote)) {
            const candidate = [
                tier,
                shared(anchor.prefix ?? '', before, true) + shared(anchor.suffix ?? '', after, false),
                -(same ? Math.abs(offset - anchor.start) : order),
            ];

            if (null === rank || better(candidate, rank)) {
                best = {
                    block: passage.block,
                    field: passage.field,
                    start: offset,
                    end: offset + length(anchor.quote),
                    quote: anchor.quote,
                    prefix: before,
                    suffix: after,
                };
                rank = candidate;
            }
        }
    });

    return best;
}

/** The path of a field element in its block's data: `text`, `items.0.items.1.content`, `content.2.1`. */
export function fieldPath(element) {
    const data = element.dataset ?? {};

    if (undefined !== data.itemField) {
        return (
            data.itemField
                .split('.')
                .map((index) => 'items.' + index)
                .join('.') + '.content'
        );
    }

    if (undefined !== data.cell) {
        return 'content.' + data.cell;
    }

    return data.field ?? null;
}

/** The fields of the editor where passages are anchored, `[{block, field, text, element}]`, in reading order. */
export function fields(root) {
    return Array.from(root.querySelectorAll(FIELDS), (element) => ({
        block: element.closest('.pbe-block')?.dataset.id ?? null,
        field: fieldPath(element),
        text: element.textContent ?? '',
        element,
    })).filter((field) => null !== field.block && null !== field.field);
}

/** The field element a node of the page is in, or null. */
export function fieldOf(node) {
    const element = 1 === node?.nodeType ? node : (node?.parentElement ?? null);

    return element?.closest(FIELDS) ?? null;
}

/** The characters of `root`'s text before a point of the DOM, `(node, offset)` as a selection gives it; null when outside. */
export function offsetIn(root, node, offset) {
    let count = 0;
    let found = false;

    const visit = (current) => {
        if (found) {
            return;
        }

        if (current === node) {
            const before =
                3 === current.nodeType
                    ? current.data.slice(0, offset)
                    : Array.from(current.childNodes, (child) => child.textContent ?? '')
                          .slice(0, offset)
                          .join('');
            count += length(before);
            found = true;
        } else if (3 === current.nodeType) {
            count += length(current.data);
        } else {
            Array.from(current.childNodes).forEach(visit);
        }
    };

    visit(root);

    return found ? count : null;
}

function textNodes(node, list = []) {
    if (3 === node.nodeType) {
        list.push(node);
    } else {
        Array.from(node.childNodes).forEach((child) => {
            textNodes(child, list);
        });
    }

    return list;
}

/** Where a character offset of `root`'s text falls, as a text node and an offset in UTF-16 units; null when beyond. */
export function pointAt(root, offset, atEnd = false) {
    let before = 0;

    for (const node of textNodes(root)) {
        const characters = Array.from(node.data);

        if (offset < before + characters.length || (atEnd && offset === before + characters.length)) {
            return { node, offset: characters.slice(0, offset - before).join('').length };
        }

        before += characters.length;
    }

    return null;
}

/** The block of an identifier in UJO blocks, wherever it is nested; null when there is none. */
export function findBlock(value, id) {
    if (Array.isArray(value)) {
        for (const item of value) {
            const found = findBlock(item, id);

            if (null !== found) {
                return found;
            }
        }
    } else if (null !== value && 'object' === typeof value) {
        if (value.id === id && 'string' === typeof value.type) {
            return value;
        }

        return findBlock(Object.values(value), id);
    }

    return null;
}

/**
 * The block of a UJO document with a passage replaced by a text, ready for the editor; null when the passage is no
 * longer at its place.
 */
export function replaceInDocument(json, anchor, replacement) {
    let document;

    try {
        document = JSON.parse(json);
    } catch {
        return null;
    }

    const block = findBlock(document?.blocks ?? [], anchor.block);
    const path = String(anchor.field ?? '').split('.');
    const key = path.pop();
    const holder = path.reduce(
        (value, step) => (null !== value && 'object' === typeof value ? (value[step] ?? null) : null),
        block?.data ?? null,
    );
    const value = null !== holder && 'object' === typeof holder ? holder[key] : undefined;

    if ('string' !== typeof value) {
        return null;
    }

    const plain = PLAIN[block.type] === anchor.field;
    const count = length(anchor.quote);

    if (slice(plain ? value : htmlText(value), anchor.start, anchor.start + count) !== anchor.quote) {
        return null;
    }

    holder[key] = plain
        ? slice(value, 0, anchor.start) + replacement + slice(value, anchor.start + count)
        : replaceHtml(value, anchor.start, count, replacement);

    return block;
}
