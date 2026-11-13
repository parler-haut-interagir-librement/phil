// The elements of the comments panel. Every text the server sends is put in as text, never as HTML.

const MENTION = 'phil--mentions-bundle--mention';

const LABELS = { edit: 'edit', reply: 'reply', page: 'pageComment' };

/** An element with attributes — `null` leaves one out — and children, texts or elements. */
export function h(document, name, attributes = {}, children = []) {
    const element = document.createElement(name);

    for (const [key, value] of Object.entries(attributes)) {
        if (null !== value && undefined !== value && false !== value) {
            element.setAttribute(key, true === value ? '' : String(value));
        }
    }

    for (const child of [children].flat()) {
        if (null !== child && undefined !== child && false !== child) {
            element.append('string' === typeof child ? document.createTextNode(child) : child);
        }
    }

    return element;
}

/** A button that runs a panel action, `data-comments-do`, on a thread or comment. */
export function action(document, label, name, id) {
    return h(
        document,
        'button',
        {
            type: 'button',
            class: 'phil-comments__action phil-comments__action--' + name,
            'data-comments-do': name,
            'data-comments-id': id,
        },
        label,
    );
}

/** What gives a text area the `@` autocomplete of phil/mentions-bundle: nothing without the address of its search. */
export function mentionAttributes(t, url) {
    return 'string' === typeof url && '' !== url
        ? {
              'data-controller': MENTION,
              ['data-' + MENTION + '-url-value']: url,
              ['data-' + MENTION + '-empty-value']: t('noMember'),
              ['data-' + MENTION + '-found-value']: t('membersFound'),
          }
        : {};
}

/**
 * A form with a text area, sent as the panel action `data-comments-form`: `open`, `page`, `reply` or `edit`; the forms
 * that stay in place, a reply and a comment on the page, have no Cancel.
 */
export function textForm(document, t, name, id, value = '', extra = null, mentions = null) {
    return h(document, 'form', { class: 'phil-comments__form', 'data-comments-form': name, 'data-comments-id': id }, [
        extra,
        h(
            document,
            'textarea',
            {
                name: 'body',
                rows: 2,
                required: true,
                'aria-label': t(LABELS[name] ?? 'comment'),
                ...mentionAttributes(t, mentions),
            },
            value,
        ),
        h(document, 'div', { class: 'phil-comments__buttons' }, [
            h(
                document,
                'button',
                { type: 'submit', class: 'phil-comments__send' },
                t('edit' === name ? 'save' : 'send'),
            ),
            'reply' === name || 'page' === name
                ? null
                : h(
                      document,
                      'button',
                      { type: 'button', 'data-comments-do': 'cancel', 'data-comments-id': id },
                      t('cancel'),
                  ),
        ]),
    ]);
}

/**
 * The replacement a thread proposes, as a deletion and an insertion. With the roles of WAI-ARIA 1.3, they are owned by
 * an element of role `suggestion`, which holds nothing else: the state and the word for a removal stay outside it.
 */
function suggestion(document, t, thread, aria) {
    const state = thread.suggestion?.state ?? null;

    if (null === state || null === (thread.anchor ?? null)) {
        return null;
    }

    const text = thread.suggestion.text;
    const deletion = h(document, 'del', {}, thread.anchor.quote);
    const insertion = '' === text ? null : h(document, 'ins', {}, text);
    const removal = '' === text ? h(document, 'em', {}, t('removal')) : null;

    return h(document, 'p', { class: 'phil-comments__suggestion phil-comments__suggestion--' + state }, [
        h(document, 'span', { class: 'phil-comments__label' }, t(state)),
        ' ',
        ...(aria
            ? [h(document, 'span', { role: 'suggestion' }, [deletion, insertion]), removal ? ' ' : null, removal]
            : [deletion, ' ', insertion ?? removal]),
    ]);
}

/** The author, date and text of a comment; with the roles of WAI-ARIA 1.3, inside an element of role `comment`. */
function commentContent(document, t, comment, editing, mentions, aria) {
    const content = [
        h(document, 'p', { class: 'phil-comments__meta' }, [
            h(document, 'strong', {}, comment.author.name),
            ' ',
            h(document, 'time', { datetime: comment.createdAt }, comment.createdAt.slice(0, 10)),
            null === comment.editedAt ? null : ' · ' + t('edited'),
        ]),
        editing === comment.id
            ? textForm(document, t, 'edit', comment.id, comment.body, null, mentions)
            : h(document, 'p', { class: 'phil-comments__body' }, comment.body),
    ];

    return aria ? [h(document, 'div', { class: 'phil-comments__content', role: 'comment' }, content)] : content;
}

/**
 * One thread: its passage — none for a thread on the whole page —, its state, orphaned when its passage is no longer
 * found, its comments and what the viewer may do. With `aria`, the roles of WAI-ARIA 1.3 are written — `comment` on
 * each comment, `suggestion` on a proposed replacement — and the passage points to the comments by `aria-details`.
 */
export function threadElement(
    document,
    t,
    thread,
    { active = false, located = true, editing = null, editable = false, mentions = null, aria = false } = {},
) {
    const resolved = 'resolved' === thread.status;
    const page = null === (thread.anchor ?? null);
    const detached = !page && (thread.detached || !located);
    const pending = 'pending' === thread.suggestion?.state;
    const details = aria ? 'phil-comments-' + thread.id + '-comments' : null;
    const classes = [
        'phil-comments__thread',
        resolved ? 'is-resolved' : null,
        page ? 'is-page' : null,
        detached ? 'is-detached' : null,
        active ? 'is-active' : null,
    ]
        .filter(Boolean)
        .join(' ');

    return h(document, 'article', { class: classes, 'data-comments-thread': thread.id, tabindex: '-1' }, [
        page
            ? null
            : h(
                  document,
                  'blockquote',
                  {
                      class: 'phil-comments__quote',
                      'data-comments-do': 'show',
                      'data-comments-id': thread.id,
                      'aria-details': details,
                  },
                  thread.anchor.quote,
              ),
        detached
            ? h(document, 'p', { class: 'phil-comments__state phil-comments__state--orphaned' }, t('detached'))
            : null,
        resolved
            ? h(
                  document,
                  'p',
                  { class: 'phil-comments__state' },
                  t('resolved', { name: thread.resolvedBy?.name ?? '' }),
              )
            : null,
        suggestion(document, t, thread, aria),
        h(
            document,
            'ol',
            { class: 'phil-comments__comments', id: page ? null : details },
            thread.comments.map((comment) =>
                h(document, 'li', { class: 'phil-comments__comment', 'data-comments-comment': comment.id }, [
                    ...commentContent(document, t, comment, editing, mentions, aria),
                    comment.may && editing !== comment.id
                        ? h(document, 'div', { class: 'phil-comments__buttons' }, [
                              action(document, t('edit'), 'edit', comment.id),
                              action(document, t('delete'), 'delete', comment.id),
                          ])
                        : null,
                ]),
            ),
        ),
        h(document, 'div', { class: 'phil-comments__buttons' }, [
            thread.may.resolve
                ? action(document, t(resolved ? 'reopen' : 'resolve'), resolved ? 'reopen' : 'resolve', thread.id)
                : null,
            thread.may.decide && pending && editable && !detached
                ? action(document, t('accept'), 'accept', thread.id)
                : null,
            thread.may.decide && pending ? action(document, t('reject'), 'reject', thread.id) : null,
        ]),
        thread.may.reply && !resolved ? textForm(document, t, 'reply', thread.id, '', null, mentions) : null,
    ]);
}
