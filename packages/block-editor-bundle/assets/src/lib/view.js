// The blocks drawn in the page: one element per block, its editable fields marked with what they hold, nested lists
// drawn by the same functions. These need a browser.

import { isSafeUrl, sanitize, textOf } from './inline.js';
import { childLists } from './model.js';
import { isKnown } from './tools.js';

/** An element with attributes — `false` and `null` left out — and children. */
export function h(document, tag, attributes = {}, children = []) {
    const element = document.createElement(tag);

    for (const [name, value] of Object.entries(attributes)) {
        if (false === value || null === value || undefined === value) {
            continue;
        }

        if ('text' === name) {
            element.textContent = value;
        } else {
            element.setAttribute(name, true === value ? '' : String(value));
        }
    }

    element.append(...children.filter((child) => null !== child && undefined !== child));

    return element;
}

/**
 * An editable field of inline HTML: its content sanitized again before it is drawn, `contenteditable` unless the
 * editor is read-only.
 */
function rich(context, tag, attributes, html, placeholder = null) {
    const element = h(context.document, tag, {
        class: ('pbe-rich ' + (attributes.class ?? '')).trim(),
        contenteditable: context.readOnly ? null : 'true',
        'data-placeholder': placeholder,
        spellcheck: context.readOnly ? null : 'true',
        ...Object.fromEntries(Object.entries(attributes).filter(([name]) => 'class' !== name)),
    });
    element.innerHTML = sanitize(html ?? '', context.marks);

    return element;
}

/** A list of blocks: its key, its blocks, and for an empty nested list a button that adds one. */
export function listElement(context, key, blocks, emptyLabel = null) {
    const list = h(context.document, 'div', { class: 'pbe-list', 'data-list': key });

    for (const block of blocks) {
        list.append(blockElement(context, block));
    }

    if (0 === blocks.length && null !== emptyLabel && !context.readOnly) {
        list.append(
            h(context.document, 'button', { type: 'button', class: 'pbe-empty', 'data-empty': key, text: emptyLabel }),
        );
    }

    return list;
}

/** A block: its gutter of buttons, unless read-only, and the body of its type. */
export function blockElement(context, block) {
    const { document, t } = context;
    const element = h(document, 'div', {
        class: 'pbe-block pbe-block--' + block.type,
        'data-id': block.id,
        'data-type': block.type,
        tabindex: '-1',
    });

    if (!context.readOnly) {
        element.append(
            h(document, 'div', { class: 'pbe-gutter', contenteditable: 'false' }, [
                h(document, 'button', {
                    type: 'button',
                    class: 'pbe-add',
                    'aria-label': t('add'),
                    title: t('add'),
                    text: '+',
                }),
                h(document, 'button', {
                    type: 'button',
                    class: 'pbe-handle',
                    draggable: 'true',
                    'aria-label': t('handle'),
                    title: t('handle'),
                    'aria-haspopup': 'menu',
                    text: '⋮⋮',
                }),
            ]),
        );
    }

    element.append(h(document, 'div', { class: 'pbe-body' }, [body(context, block)]));

    return element;
}

function body(context, block) {
    const { document, t } = context;
    const data = block.data;

    if (!isKnown(block.type)) {
        return h(document, 'div', { class: 'pbe-foreign', text: t('foreign', { type: block.type }) });
    }

    switch (block.type) {
        case 'paragraph':
            return rich(
                context,
                'div',
                { class: 'pbe-paragraph', 'data-field': 'text' },
                data.text,
                context.placeholder,
            );
        case 'header':
            return rich(
                context,
                'h' + Math.min(6, Math.max(1, data.level ?? 2)),
                { class: 'pbe-header', 'data-field': 'text' },
                data.text,
                t('heading' + Math.min(3, data.level ?? 2)),
            );
        case 'quote':
            return h(document, 'blockquote', { class: 'pbe-quote pbe-align-' + (data.alignment ?? 'left') }, [
                rich(context, 'div', { class: 'pbe-quote__text', 'data-field': 'text' }, data.text, t('quote')),
                rich(
                    context,
                    'div',
                    { class: 'pbe-quote__caption', 'data-field': 'caption' },
                    data.caption,
                    t('author'),
                ),
            ]);
        case 'alert':
            return h(document, 'div', { class: 'pbe-alert pbe-alert--' + (data.type ?? 'info'), role: 'note' }, [
                rich(context, 'div', { class: 'pbe-alert__text', 'data-field': 'text' }, data.text, t('alert')),
            ]);
        case 'code':
            return code(context, data);
        case 'delimiter':
            return h(document, 'hr', { class: 'pbe-delimiter' });
        case 'list':
            return listItems(context, data, data.items, []);
        case 'table':
            return table(context, data);
        case 'image':
            return image(context, block);
        case 'toggle':
            return toggle(context, block);
        default:
            return h(
                document,
                'div',
                { class: 'pbe-columns', style: '--pbe-columns: ' + data.cols.length },
                childLists(block).map((child) =>
                    h(document, 'div', { class: 'pbe-column' }, [
                        listElement(context, child.key, child.list, t('emptyColumn')),
                    ]),
                ),
            );
    }
}

function code(context, data) {
    const { document, t } = context;
    const area = h(document, 'textarea', {
        class: 'pbe-code__source',
        'data-field': 'code',
        rows: String(Math.max(2, data.code.split('\n').length)),
        spellcheck: 'false',
        readonly: context.readOnly,
        'aria-label': t('code'),
    });
    area.value = data.code;

    return h(document, 'div', { class: 'pbe-code' }, [
        h(document, 'input', {
            class: 'pbe-code__language',
            'data-field': 'language',
            value: data.language ?? '',
            placeholder: t('language'),
            'aria-label': t('language'),
            readonly: context.readOnly,
            maxlength: '32',
        }),
        area,
    ]);
}

function listItems(context, data, items, path) {
    const { document, t } = context;
    const ordered = 'ordered' === data.style;

    return h(
        document,
        ordered ? 'ol' : 'ul',
        {
            class: 'pbe-items pbe-items--' + data.style,
            start: ordered && 0 === path.length && (data.meta?.start ?? 1) !== 1 ? data.meta.start : null,
        },
        items.map((item, index) => {
            const at = [...path, index].join('.');

            return h(document, 'li', { class: 'pbe-item', 'data-item': at }, [
                'checklist' === data.style
                    ? h(document, 'input', {
                          type: 'checkbox',
                          class: 'pbe-item__check',
                          'data-check': at,
                          checked: true === item.meta?.checked,
                          disabled: context.readOnly,
                          'aria-label': t('done'),
                      })
                    : null,
                rich(context, 'div', { class: 'pbe-item__content', 'data-item-field': at }, item.content),
                (item.items ?? []).length > 0 ? listItems(context, data, item.items, [...path, index]) : null,
            ]);
        }),
    );
}

function table(context, data) {
    const { document } = context;

    return h(document, 'table', { class: 'pbe-table' + (data.stretched ? ' pbe-table--stretched' : '') }, [
        h(
            document,
            'tbody',
            {},
            data.content.map((row, r) =>
                h(
                    document,
                    'tr',
                    {},
                    row.map((cell, c) =>
                        h(document, data.withHeadings && 0 === r ? 'th' : 'td', {}, [
                            rich(context, 'div', { class: 'pbe-table__cell', 'data-cell': r + '.' + c }, cell),
                        ]),
                    ),
                ),
            ),
        ),
    ]);
}

function image(context, block) {
    const { document, t } = context;
    const data = block.data;
    const url = data.file?.url ?? '';

    if ('' !== url && isSafeUrl(url, { media: true })) {
        return h(
            document,
            'figure',
            {
                class: [
                    'pbe-image',
                    data.withBorder ? 'pbe-image--border' : '',
                    data.withBackground ? 'pbe-image--background' : '',
                    data.stretched ? 'pbe-image--stretched' : '',
                ]
                    .join(' ')
                    .trim(),
            },
            [
                h(document, 'img', { src: url, alt: textOf(data.caption ?? ''), loading: 'lazy' }),
                rich(
                    context,
                    'figcaption',
                    { class: 'pbe-image__caption', 'data-field': 'caption' },
                    data.caption,
                    t('caption'),
                ),
            ],
        );
    }

    if (context.readOnly) {
        return h(document, 'div', { class: 'pbe-image pbe-image--empty' });
    }

    const uploading = context.uploading.has(block.id);

    return h(document, 'div', { class: 'pbe-picker', 'data-drop': block.id }, [
        null === context.uploadUrl
            ? null
            : h(document, 'label', { class: 'pbe-picker__upload' }, [
                  h(document, 'span', { text: uploading ? t('uploading') : t('upload') }),
                  h(document, 'input', {
                      type: 'file',
                      accept: 'image/*',
                      'data-upload': block.id,
                      disabled: uploading,
                      class: 'pbe-picker__file',
                  }),
                  h(document, 'span', { class: 'pbe-picker__hint', text: t('dropImage') }),
              ]),
        h(document, 'div', { class: 'pbe-picker__url' }, [
            h(document, 'input', {
                type: 'url',
                class: 'pbe-picker__address',
                'data-image-url': block.id,
                placeholder: t('imageUrl'),
                'aria-label': t('imageUrl'),
            }),
            h(document, 'button', { type: 'button', 'data-image-insert': block.id, text: t('imageInsert') }),
        ]),
    ]);
}

function toggle(context, block) {
    const { document, t } = context;
    const open = true === block.data.open;

    return h(document, 'div', { class: 'pbe-toggle' + (open ? ' pbe-toggle--open' : '') }, [
        h(document, 'div', { class: 'pbe-toggle__head' }, [
            h(document, 'button', {
                type: 'button',
                class: 'pbe-toggle__arrow',
                'data-toggle': block.id,
                'aria-expanded': open ? 'true' : 'false',
                'aria-label': open ? t('closeToggle') : t('openToggle'),
                text: '▸',
            }),
            rich(context, 'div', { class: 'pbe-toggle__text', 'data-field': 'text' }, block.data.text, t('toggle')),
        ]),
        h(document, 'div', { class: 'pbe-toggle__body', hidden: !open }, [
            listElement(context, block.id, block.data.blocks, t('emptyToggle')),
        ]),
    ]);
}
