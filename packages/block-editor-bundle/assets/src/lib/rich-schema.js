// The ProseMirror schema of a rich field, as plain specs drawn from the inline tools of phil/ujo's `rich` profile:
// one mark per tool, read from the elements the tool accepts and written in its canonical form, a line break as the
// only node besides text. Used by the ProseMirror trial (`richEngine: 'prosemirror'`); pure functions, without the
// DOM and without ProseMirror, so that the rules are tested under Node.

import { cleanAttribute, match } from './inline.js';

/** The marks the inline style of pasted text stands for, as Word and Google Docs write them. */
const STYLE_RULES = {
    bold: [['font-weight', /^(bold|bolder|[6-9]00)$/i]],
    italic: [['font-style', /^italic$/i]],
    underline: [
        ['text-decoration', /\bunderline\b/i],
        ['text-decoration-line', /\bunderline\b/i],
    ],
    strikethrough: [
        ['text-decoration', /\bline-through\b/i],
        ['text-decoration-line', /\bline-through\b/i],
    ],
    superscript: [['vertical-align', /^super$/i]],
    subscript: [['vertical-align', /^sub$/i]],
};

/** A weight that is not bold, which Google Docs writes on the `<b>` wrapping a whole copied passage. */
const NORMAL_WEIGHT = /font-weight\s*:\s*(normal|[1-5]00)\b/i;

/** The attributes of an element, by name; works on anything with `attributes` as name and value pairs. */
export function attributesOf(element) {
    return Object.fromEntries(Array.from(element.attributes ?? [], (attribute) => [attribute.name, attribute.value]));
}

/**
 * The mark attributes an element gives a tool, or `false` when the tool does not take it: the same rules as the
 * sanitizer, an address or a token that does not pass leaving the element unread. With `styles`, a bold element of
 * normal weight is not bold.
 */
export function markAttributes(tool, element, { styles = false } = {}) {
    const attributes = attributesOf(element);
    const name = String(element.tagName ?? element.nodeName ?? '').toLowerCase();

    if (styles && 'bold' === tool.name && NORMAL_WEIGHT.test(attributes.style ?? '')) {
        return false;
    }

    const found = match([tool], name, attributes);

    if (null === found) {
        return false;
    }

    return Object.fromEntries(Object.keys(tool.attributes ?? {}).map((key) => [key, found.attributes[key] ?? null]));
}

/** The element a mark is written as: the tool's tag, its class, and the attributes that still pass. */
export function markDom(tool, attrs = {}) {
    const written = undefined === tool.className ? {} : { class: tool.className };

    for (const [key, kind] of Object.entries(tool.attributes ?? {})) {
        const value = null === (attrs[key] ?? null) ? null : cleanAttribute(kind, String(attrs[key]));

        if (null !== value) {
            written[key] = value;
        }
    }

    return [tool.tag, written, 0];
}

/** The style rules of a tool, as ProseMirror parse rules over a style property's value. */
export function styleRules(tool) {
    return (STYLE_RULES[tool.name] ?? []).map(([style, pattern]) => ({
        style,
        getAttrs: (value) => (pattern.test(String(value).trim()) ? null : false),
    }));
}

/**
 * The node and mark specs of a field's schema, for `new Schema(schemaSpec(tools))`: the document holds inline
 * content only, links of every kind exclude each other and do not grow as the writer types at their end.
 */
export function schemaSpec(tools, { styles = true } = {}) {
    const nodes = {
        doc: { content: 'inline*' },
        text: { group: 'inline' },
    };

    if (tools.some((tool) => 'lineBreak' === tool.name)) {
        nodes.hard_break = {
            inline: true,
            group: 'inline',
            selectable: false,
            parseDOM: [{ tag: 'br' }],
            toDOM: () => ['br'],
        };
    }

    const links = tools.filter((tool) => 'a' === tool.tag).map((tool) => tool.name);
    const marks = {};

    for (const tool of tools.filter((candidate) => 'br' !== candidate.tag)) {
        const link = 'a' === tool.tag;
        marks[tool.name] = {
            attrs: Object.fromEntries(Object.keys(tool.attributes ?? {}).map((key) => [key, { default: null }])),
            inclusive: !link,
            ...(link ? { excludes: links.join(' ') } : {}),
            parseDOM: [
                ...tool.accepts.map((tag) => ({
                    tag,
                    getAttrs: (element) => markAttributes(tool, element, { styles }),
                })),
                ...(styles ? styleRules(tool) : []),
            ],
            toDOM: (mark) => markDom(tool, mark.attrs),
        };
    }

    return { nodes, marks };
}
