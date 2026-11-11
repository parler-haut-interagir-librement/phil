// What a writer pastes, turned into blocks of the editor's toolset: HTML from a page, Word or Google Docs read
// element by element — paragraphs, headings, lists (Word's included), quotes, code, tables, rules and images —, its
// inline text cleaned by the rules of phil/ujo, its styles turned into marks; plain text split into paragraphs.
// Pure functions, without the DOM.

import { escapeHtml, fromText, isBlank, isSafeUrl, profile, sanitize, tokenize } from './inline.js';
import { newId, newItem } from './model.js';

const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

const BLOCKS = new Set([
    ...HEADINGS,
    'p',
    'div',
    'li',
    'ul',
    'ol',
    'blockquote',
    'pre',
    'table',
    'thead',
    'tbody',
    'tfoot',
    'tr',
    'td',
    'th',
    'caption',
    'hr',
    'img',
    'figure',
    'figcaption',
    'section',
    'article',
    'header',
    'footer',
    'aside',
    'main',
    'nav',
    'dl',
    'dt',
    'dd',
    'address',
    'center',
    'body',
    'html',
]);

/** Plain text as paragraphs: a blank line between two, a single new line kept as a line break. */
export function textBlocks(text, { makeId = newId } = {}) {
    return text
        .replace(/\r\n?/g, '\n')
        .split(/\n[ \t]*\n+/)
        .map((part) => part.replace(/^\n+|\n+$/g, ''))
        .filter((part) => '' !== part.trim())
        .map((part) => ({ id: makeId(), type: 'paragraph', data: { text: fromText(part) } }));
}

/** Inline HTML without the spaces and line breaks at its ends. */
function trim(html) {
    return html.replace(/^(?:[\s ]|<br>)+|(?:[\s ]|<br>)+$/g, '');
}

function wordLevel(attributes) {
    const style = attributes.style ?? '';
    const level = /mso-list\s*:[^;]*level(\d+)/i.exec(style);

    return /\bMsoListParagraph/i.test(attributes.class ?? '') || null !== level ? Number(level?.[1] ?? 1) : 0;
}

/**
 * The blocks of pasted HTML, of the types the toolset offers; what it does not offer falls back to paragraphs.
 *
 * @param {string} html
 * @param {{tools?: string[], inlineTools?: string[], makeId?: () => string}} options
 */
export function pasteBlocks(html, { tools = ['paragraph'], inlineTools = [], makeId = newId } = {}) {
    const marks = profile(inlineTools);
    const blocks = [];
    const contexts = [];
    let buffer = '';
    let skipped = '';
    let skipping = 0;
    let ignore = null;
    let list = null;
    let word = null;
    let table = null;
    let code = null;

    const block = (type, data) => blocks.push({ id: makeId(), type, data });
    const has = (type) => tools.includes(type);
    const innermost = (...kinds) => contexts.findLast((context) => kinds.includes(context.kind));

    const emitList = (built) => {
        if (has('list')) {
            block('list', { style: built.style, meta: {}, items: built.root });
        } else {
            const contents = (items) => items.flatMap((item) => [item.content, ...contents(item.items)]);
            contents(built.root)
                .filter((content) => !isBlank(content))
                .forEach((content) => {
                    block('paragraph', { text: content });
                });
        }
    };

    const endWordList = () => {
        if (null !== word) {
            emitList(word);
            word = null;
        }
    };

    const flush = () => {
        const content = trim(sanitize(buffer, marks, { styles: true }));
        const wordItem = skipped;
        buffer = '';
        skipped = '';
        const context = innermost('item', 'cell', 'header', 'quote', 'word');

        if ('item' === context?.kind || 'cell' === context?.kind) {
            context.target.content = trim(
                '' === context.target.content || '' === content
                    ? context.target.content + content
                    : context.target.content + '<br>' + content,
            );

            return;
        }

        if (isBlank(content)) {
            return;
        }

        if ('word' === context?.kind) {
            word ??= {
                style: /^\s*[0-9A-Za-z]{1,4}[.)]/.test(wordItem) ? 'ordered' : 'unordered',
                root: [],
                levels: [],
            };
            const level = Math.min(context.level, word.levels.length + 1);
            word.levels.length = level - 1;
            const items = 0 === word.levels.length ? word.root : word.levels.at(-1);
            const item = newItem(word.style, content);
            items.push(item);
            word.levels.push(item.items);

            return;
        }

        endWordList();

        if ('header' === context?.kind && has('header')) {
            block('header', { text: content, level: context.level });
        } else if ('quote' === context?.kind && has('quote')) {
            block('quote', { text: content, caption: '', alignment: 'left' });
        } else {
            block('paragraph', { text: content });
        }
    };

    for (const token of tokenize(html, { conditions: true })) {
        if ('if' === token.kind) {
            skipping = token.condition.includes('supportlists') ? 1 : 'endif' === token.condition ? 0 : skipping;
            continue;
        }

        if (null !== ignore) {
            ignore.depth +=
                'start' === token.kind && token.name === ignore.name
                    ? 1
                    : 'end' === token.kind && token.name === ignore.name
                      ? -1
                      : 0;
            skipped += 'text' === token.kind ? token.text : '';
            ignore = 0 === ignore.depth ? null : ignore;
            continue;
        }

        if (1 === skipping) {
            skipped += 'text' === token.kind ? token.text : '';
            continue;
        }

        if ('text' === token.kind) {
            if (null !== code) {
                code.text += token.text;
            } else {
                buffer += escapeHtml(token.text.replace(/[\t\n\r ]+/g, ' '));
            }

            continue;
        }

        const name = token.name;

        if ('start' === token.kind && /mso-list\s*:\s*ignore/i.test(token.attributes.style ?? '')) {
            ignore = { name, depth: 1 };
            continue;
        }

        if ('start' === token.kind && 'input' === name && 'checkbox' === (token.attributes.type ?? '').toLowerCase()) {
            const item = innermost('item');

            if (undefined !== item && null !== list) {
                list.style = 'checklist';
                item.target.meta = { checked: 'checked' in token.attributes };
            }

            continue;
        }

        if (!BLOCKS.has(name)) {
            if (null !== code) {
                code.text += 'br' === name ? '\n' : '';
            } else if ('br' === name && /<br>\s*$/.test(buffer)) {
                buffer = buffer.replace(/<br>\s*$/, '');
                flush();
            } else if ('br' === name) {
                buffer += '<br>';
            } else {
                buffer +=
                    'start' === token.kind
                        ? '<' +
                          name +
                          Object.entries(token.attributes)
                              .map(([key, value]) => ' ' + key + '="' + escapeHtml(value) + '"')
                              .join('') +
                          '>'
                        : '</' + name + '>';
            }

            continue;
        }

        if (null === code) {
            flush();
        }

        if ('end' === token.kind) {
            const at = contexts.findLastIndex((context) => context.name === name);

            if (-1 === at) {
                continue;
            }

            for (const context of contexts.splice(at).reverse()) {
                if ('list' === context.kind) {
                    list.levels.pop();

                    if (0 === list.levels.length) {
                        emitList(list);
                        list = null;
                    }
                } else if ('row' === context.kind) {
                    table.rows.push(context.target);
                } else if ('table' === context.kind) {
                    const width = Math.max(1, ...table.rows.map((row) => row.length));
                    const rows = table.rows
                        .filter((row) => row.length > 0)
                        .map((row) => [...row.map((cell) => cell.content), ...Array(width - row.length).fill('')]);

                    if (has('table') && rows.length > 0) {
                        block('table', { withHeadings: table.headings, stretched: false, content: rows });
                    } else {
                        rows.flat()
                            .filter((cell) => !isBlank(cell))
                            .forEach((cell) => {
                                block('paragraph', { text: cell });
                            });
                    }

                    table = null;
                } else if ('code' === context.kind) {
                    const source = code.text.replace(/^\n|\n$/g, '');

                    if (has('code')) {
                        block('code', { code: source });
                    } else if ('' !== source.trim()) {
                        block('paragraph', { text: fromText(source) });
                    }

                    code = null;
                }
            }

            continue;
        }

        if (null !== code) {
            continue;
        }

        if ('hr' === name) {
            endWordList();

            if (has('delimiter')) {
                block('delimiter', {});
            }
        } else if ('img' === name) {
            const source = token.attributes.src ?? '';

            if (has('image') && isSafeUrl(source, { media: true })) {
                endWordList();
                block('image', {
                    file: { url: source },
                    caption: '',
                    withBorder: false,
                    withBackground: false,
                    stretched: false,
                });
            }
        } else if ('ul' === name || 'ol' === name) {
            endWordList();

            if (null === list) {
                list = { style: 'ol' === name ? 'ordered' : 'unordered', root: [], levels: [] };
                list.levels.push(list.root);
            } else {
                const items = list.levels.at(-1);

                if (0 === items.length) {
                    items.push(newItem(list.style));
                }

                list.levels.push(items.at(-1).items);
            }

            contexts.push({ kind: 'list', name });
        } else if ('li' === name && null !== list) {
            const item = newItem(list.style);
            list.levels.at(-1).push(item);
            contexts.push({ kind: 'item', name, target: item });
        } else if ('table' === name && null === table) {
            endWordList();
            table = { rows: [], headings: false };
            contexts.push({ kind: 'table', name });
        } else if ('tr' === name && null !== table) {
            contexts.push({ kind: 'row', name, target: [] });
        } else if (('td' === name || 'th' === name) && undefined !== innermost('row')) {
            const cell = { content: '' };
            innermost('row').target.push(cell);
            table.headings ||= 'th' === name && 0 === table.rows.length;
            contexts.push({ kind: 'cell', name, target: cell });
        } else if ('pre' === name) {
            endWordList();
            code = { text: '' };
            contexts.push({ kind: 'code', name });
        } else if (HEADINGS.includes(name)) {
            contexts.push({ kind: 'header', name, level: Number(name[1]) });
        } else if ('blockquote' === name) {
            contexts.push({ kind: 'quote', name });
        } else if (wordLevel(token.attributes) > 0) {
            contexts.push({ kind: 'word', name, level: wordLevel(token.attributes) });
        } else {
            contexts.push({ kind: 'block', name });
        }
    }

    if (null === code) {
        flush();
    }

    endWordList();

    if (null !== list) {
        emitList(list);
    }

    return blocks;
}
