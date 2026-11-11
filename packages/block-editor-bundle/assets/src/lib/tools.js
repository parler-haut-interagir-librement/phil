// The block tools: the data each type starts with, as phil/ujo writes it, its text, its conversions to other types,
// the entries of the slash menu and the actions of the block menu. Pure functions, without the DOM.

import { fromText, isBlank, textOf } from './inline.js';
import { addColumn, addRow, flatItems, newId, newItem, removeColumn, removeRow, restyleList } from './model.js';

/** Every block type, in the order of the slash menu; the same list as the bundle's `Toolset::BLOCK_TOOLS`. */
export const BLOCK_TOOLS = [
    'paragraph',
    'header',
    'list',
    'quote',
    'code',
    'alert',
    'toggle',
    'columns',
    'table',
    'image',
    'delimiter',
];

/** Every inline tool, in the order of the toolbar; the same list as the bundle's `Toolset::INLINE_TOOLS`. */
export const INLINE_TOOLS = [
    'bold',
    'italic',
    'underline',
    'strikethrough',
    'inlineCode',
    'link',
    'marker',
    'textColor',
    'superscript',
    'subscript',
    'mention',
    'wikiLink',
    'lineBreak',
];

export const PRESETS = {
    full: { tools: BLOCK_TOOLS, inlineTools: INLINE_TOOLS },
    reduced: { tools: ['paragraph', 'header', 'list', 'quote'], inlineTools: ['bold', 'italic', 'link', 'lineBreak'] },
};

const text = (data) => data.text;

/** Each type: `create(options)`, and for the types holding text, `text(data)` and `withText(html, options)`. */
export const TOOLS = {
    paragraph: {
        create: () => ({ text: '' }),
        text,
        withText: (html) => ({ text: html }),
        isEmpty: (data) => isBlank(data.text),
    },
    header: {
        create: ({ level = 2 } = {}) => ({ text: '', level }),
        text,
        withText: (html, { level = 2 } = {}) => ({ text: html, level }),
        isEmpty: (data) => isBlank(data.text),
    },
    list: {
        create: ({ style = 'unordered', checked = false } = {}) => ({
            style,
            meta: {},
            items: [{ ...newItem(style), ...('checklist' === style ? { meta: { checked } } : {}) }],
        }),
        text: (data) =>
            flatItems(data.items)
                .map((entry) => entry.item.content)
                .join('<br>'),
        withText: (html, { style = 'unordered' } = {}) => ({
            style,
            meta: {},
            items: html.split('<br>').map((line) => newItem(style, line)),
        }),
        isEmpty: (data) => flatItems(data.items).every((entry) => isBlank(entry.item.content)),
    },
    quote: {
        create: () => ({ text: '', caption: '', alignment: 'left' }),
        text,
        withText: (html) => ({ text: html, caption: '', alignment: 'left' }),
        isEmpty: (data) => isBlank(data.text) && isBlank(data.caption),
    },
    code: {
        create: ({ language = '' } = {}) => ('' === language ? { code: '' } : { code: '', language }),
        text: (data) => fromText(data.code),
        withText: (html) => ({ code: textOf(html) }),
        isEmpty: (data) => '' === data.code.trim(),
    },
    alert: {
        create: ({ type = 'info' } = {}) => ({ type, align: 'left', text: '' }),
        text,
        withText: (html, { type = 'info' } = {}) => ({ type, align: 'left', text: html }),
        isEmpty: (data) => isBlank(data.text),
    },
    toggle: {
        create: () => ({ text: '', open: true, blocks: [] }),
        text,
        withText: (html) => ({ text: html, open: true, blocks: [] }),
        isEmpty: (data) => isBlank(data.text) && 0 === data.blocks.length,
    },
    columns: {
        create: ({ count = 2 } = {}) => ({ cols: Array.from({ length: count }, () => ({ blocks: [] })) }),
        isEmpty: (data) => data.cols.every((column) => 0 === column.blocks.length),
    },
    table: {
        create: () => ({
            withHeadings: false,
            stretched: false,
            content: [
                ['', ''],
                ['', ''],
            ],
        }),
        isEmpty: (data) => data.content.every((row) => row.every(isBlank)),
    },
    image: {
        create: () => ({ file: { url: '' }, caption: '', withBorder: false, withBackground: false, stretched: false }),
        isEmpty: (data) => '' === (data.file?.url ?? ''),
    },
    delimiter: {
        create: () => ({}),
        isEmpty: () => false,
    },
};

/** Whether a block shows nothing and is left out of the saved document; a type the editor does not know never is. */
export function isEmpty(block) {
    return TOOLS[block.type]?.isEmpty(block.data) ?? false;
}

/** Whether the editor edits a type; others are kept as they are, moved and removed only. */
export function isKnown(type) {
    return Object.hasOwn(TOOLS, type);
}

/** Whether a type holds text, and so converts to and from the other types holding text. */
export function holdsText(type) {
    return undefined !== TOOLS[type]?.text;
}

/** A new block of a type. */
export function createBlock(type, options = {}, makeId = newId) {
    return { id: makeId(), type, data: TOOLS[type].create(options) };
}

/**
 * The blocks a block becomes in another type, or null when either type holds no text. A list becomes one block per
 * item, a toggle leaves its nested blocks after it, text becomes one list item per line; the first block keeps the
 * identifier and the tunes.
 */
export function convert(block, type, options = {}, makeId = newId) {
    if (!holdsText(block.type) || !holdsText(type)) {
        return null;
    }

    const first = { ...block, type };
    const after = 'toggle' === block.type ? block.data.blocks : [];

    if ('list' === block.type && 'list' === type) {
        return [{ ...first, data: restyleList(block.data, options.style ?? 'unordered') }];
    }

    if ('list' === block.type) {
        const [head, ...rest] = flatItems(block.data.items).map((entry) => entry.item.content);

        return [
            { ...first, data: TOOLS[type].withText(head, options) },
            ...rest.map((html) => ({ id: makeId(), type, data: TOOLS[type].withText(html, options) })),
        ];
    }

    return [{ ...first, data: TOOLS[type].withText(TOOLS[block.type].text(block.data), options) }, ...after];
}

/**
 * The entries of the slash menu for the tools offered: a key, the type and the options it is created with, the
 * label's key in the messages, and words that find it.
 */
export function slashItems(tools) {
    const entries = [
        ['paragraph', 'paragraph', {}, ['text', 'p', 'texte']],
        ['heading1', 'header', { level: 1 }, ['h1', 'title', 'heading', 'titre']],
        ['heading2', 'header', { level: 2 }, ['h2', 'heading', 'subtitle', 'titre']],
        ['heading3', 'header', { level: 3 }, ['h3', 'heading', 'titre']],
        ['unordered', 'list', { style: 'unordered' }, ['ul', 'bullet', 'list', 'puces', 'liste']],
        ['ordered', 'list', { style: 'ordered' }, ['ol', 'number', 'list', 'numérotée', 'liste']],
        ['checklist', 'list', { style: 'checklist' }, ['todo', 'task', 'check', 'tâche', 'liste']],
        ['quote', 'quote', {}, ['blockquote', 'citation']],
        ['code', 'code', {}, ['pre', 'snippet', 'source']],
        ['alert', 'alert', { type: 'info' }, ['callout', 'note', 'info', 'encadré']],
        ['toggle', 'toggle', {}, ['details', 'collapse', 'accordion', 'dépliant']],
        ['columns2', 'columns', { count: 2 }, ['grid', 'layout', 'colonnes']],
        ['columns3', 'columns', { count: 3 }, ['grid', 'layout', 'colonnes']],
        ['table', 'table', {}, ['grid', 'cells', 'tableau']],
        ['image', 'image', {}, ['picture', 'photo', 'upload']],
        ['delimiter', 'delimiter', {}, ['divider', 'hr', 'separator', 'line', 'séparateur']],
    ];

    return entries
        .filter(([, type]) => tools.includes(type))
        .map(([key, type, options, keywords]) => ({ key, type, options, keywords }));
}

/** The entries of the "Turn into" menu of a block: the slash menu's entries of types holding text. */
export function conversions(block, tools) {
    return holdsText(block.type) ? slashItems(tools).filter((entry) => holdsText(entry.type)) : [];
}

const flag = (key, name) => ({
    key,
    active: (data) => true === data[name],
    apply: (data) => ({ ...data, [name]: !data[name] }),
});

const choice = (key, name, value) => ({
    key,
    active: (data) => data[name] === value,
    apply: (data) => ({ ...data, [name]: value }),
});

const copy = (data) => JSON.parse(JSON.stringify(data));

/**
 * The actions of a block's menu that belong to its type: a key for the label, whether it is on, and the data it
 * gives. Header levels, list styles, alert types, quote alignment, image options, table rows and columns, column
 * counts.
 */
export function blockActions(block) {
    switch (block.type) {
        case 'header':
            return [1, 2, 3, 4, 5, 6].map((level) => choice('level' + level, 'level', level));
        case 'list':
            return ['unordered', 'ordered', 'checklist'].map((style) => ({
                key: style,
                active: (data) => data.style === style,
                apply: (data) => restyleList(data, style),
            }));
        case 'alert':
            return ['info', 'success', 'warning', 'danger'].map((type) => choice('alert.' + type, 'type', type));
        case 'quote':
            return ['left', 'center'].map((alignment) => choice('align.' + alignment, 'alignment', alignment));
        case 'image':
            return [
                flag('withBorder', 'withBorder'),
                flag('withBackground', 'withBackground'),
                flag('stretched', 'stretched'),
                { key: 'replaceImage', active: () => false, apply: (data) => ({ ...data, file: { url: '' } }) },
            ];
        case 'table':
            return [
                flag('withHeadings', 'withHeadings'),
                flag('stretched', 'stretched'),
                { key: 'addRow', active: () => false, apply: (data) => addRow(copy(data)) },
                { key: 'addColumn', active: () => false, apply: (data) => addColumn(copy(data)) },
                { key: 'removeRow', active: () => false, apply: (data) => removeRow(copy(data)) },
                { key: 'removeColumn', active: () => false, apply: (data) => removeColumn(copy(data)) },
            ];
        case 'columns':
            return [2, 3, 4].map((count) => ({
                key: 'columns' + count,
                active: (data) => data.cols.length === count,
                apply: (data) => columnCount(data, count),
            }));
        default:
            return [];
    }
}

/** Columns made `count`: new ones empty, the blocks of those taken away moved to the last one kept. */
export function columnCount(data, count) {
    const cols = copy(data).cols;

    while (cols.length < count) {
        cols.push({ blocks: [] });
    }

    const removed = cols.splice(count);
    cols.at(-1).blocks.push(...removed.flatMap((column) => column.blocks));

    return { ...data, cols };
}
