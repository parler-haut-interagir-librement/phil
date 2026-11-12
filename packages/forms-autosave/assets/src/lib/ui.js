// The status and notice a form gets when its template has none: a polite live region the controller writes what it
// did in, and the notice of a restored or newer draft, hidden, with its buttons wired to the controller's actions.

/** The labels in English, for a page that gives none. */
export const ENGLISH = {
    saved: 'Draft saved on this device.',
    savedServer: 'Draft saved.',
    restored: 'Your unsaved input was restored.',
    discarded: 'The draft was discarded.',
    conflict: 'A newer draft was saved from another device or tab.',
    tooLarge: 'This form is too large to be kept as a draft.',
    unavailable: 'Drafts cannot be kept in this browser.',
    discard: 'Discard the draft',
    dismiss: 'Keep it',
    useServer: 'Use the newer draft',
};

/** The labels a page gives, each text in place of the English one. */
export function labels(given) {
    const merged = { ...ENGLISH };

    for (const [name, text] of Object.entries(given ?? {})) {
        if (Object.hasOwn(ENGLISH, name) && 'string' === typeof text && '' !== text) {
            merged[name] = text;
        }
    }

    return merged;
}

/** The status and the notice, as the bundle's template draws them, for the controller `identifier`. */
export function buildUi(doc, texts, identifier) {
    const target = `data-${identifier}-target`;
    const element = (tag, attributes, text = '') => {
        const node = doc.createElement(tag);

        for (const [name, value] of Object.entries(attributes)) {
            node.setAttribute(name, value);
        }

        node.textContent = text;

        return node;
    };
    const button = (name, hidden) => {
        const node = element(
            'button',
            { type: 'button', [target]: name, 'data-action': `${identifier}#${name}` },
            texts[name],
        );
        node.hidden = hidden;

        return node;
    };

    const notice = element('div', { class: 'phil-autosave__notice', role: 'group', [target]: 'notice' });
    notice.hidden = true;
    notice.append(
        element('p', { [target]: 'message' }),
        button('useServer', true),
        button('discard', false),
        button('dismiss', false),
    );
    const root = element('div', { class: 'phil-autosave' });
    root.append(
        element('p', { class: 'phil-autosave__status', role: 'status', 'aria-live': 'polite', [target]: 'status' }),
        notice,
    );

    return root;
}
