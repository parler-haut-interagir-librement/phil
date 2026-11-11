// The editor's keyboard: the shortcuts, named from a key event, and the Markdown-like marks that turn a block into
// another type as they are typed. Pure functions, without the DOM.

const SHORTCUTS = {
    'mod+b': 'bold',
    'mod+i': 'italic',
    'mod+u': 'underline',
    'mod+shift+x': 'strikethrough',
    'mod+e': 'inlineCode',
    'mod+k': 'link',
    'mod+shift+h': 'marker',
    'mod+.': 'superscript',
    'mod+,': 'subscript',
    'mod+d': 'duplicate',
    'mod+shift+arrowup': 'moveUp',
    'mod+shift+arrowdown': 'moveDown',
    'mod+alt+0': 'paragraph',
    'mod+alt+1': 'heading1',
    'mod+alt+2': 'heading2',
    'mod+alt+3': 'heading3',
    'mod+alt+4': 'checklist',
    'mod+alt+5': 'unordered',
    'mod+alt+6': 'ordered',
    'mod+alt+7': 'toggle',
    'mod+alt+8': 'code',
    'mod+z': 'undo',
    'mod+shift+z': 'redo',
    'mod+y': 'redo',
    'mod+a': 'selectAll',
    escape: 'selectBlock',
};

/** Whether the platform's command key is ⌘ rather than Ctrl. */
export function isApple(platform) {
    return /mac|iphone|ipad|ipod/i.test(platform ?? '');
}

/**
 * The action a key event asks for, or null. `mod` is ⌘ on Apple systems and Ctrl elsewhere; a digit is read from its
 * physical key, since Option changes the character it types on a Mac.
 */
export function shortcut(event, apple = false) {
    const mod = apple ? event.metaKey : event.ctrlKey;
    const other = apple ? event.ctrlKey : event.metaKey;

    if (other) {
        return null;
    }

    const digit = /^Digit([0-9])$/.exec(event.code ?? '');
    const key = null !== digit ? digit[1] : (event.key ?? '').toLowerCase();
    const name = [mod ? 'mod' : '', event.altKey ? 'alt' : '', event.shiftKey ? 'shift' : '', key]
        .filter((part) => '' !== part)
        .join('+');

    return SHORTCUTS[name] ?? null;
}

/**
 * The block a mark typed at the start of a block turns it into, or null: `#` to `######` and a space for headings,
 * `-`, `*` or `+` for a bulleted list, `1.` for a numbered one, `[]` or `[x]` for a to-do list, `>` for a quote,
 * `>>` for a toggle, `!` for a callout, ` ``` ` and a space for code — its language between them — and `---` for a divider.
 */
export function autoformat(text) {
    const typed = text.replace(/ /g, ' ');
    const rules = [
        [/^(#{1,6}) $/, (found) => ({ type: 'header', options: { level: found[1].length } })],
        [/^[-*+] $/, () => ({ type: 'list', options: { style: 'unordered' } })],
        [/^(\d{1,4})[.)] $/, () => ({ type: 'list', options: { style: 'ordered' } })],
        [
            /^\[( |x|X)?\] $/,
            (found) => ({
                type: 'list',
                options: { style: 'checklist', checked: undefined !== found[1] && ' ' !== found[1] },
            }),
        ],
        [/^>> $/, () => ({ type: 'toggle', options: {} })],
        [/^> $/, () => ({ type: 'quote', options: {} })],
        [/^! $/, () => ({ type: 'alert', options: { type: 'info' } })],
        [/^```([A-Za-z0-9_+#.-]{0,32}) $/, (found) => ({ type: 'code', options: { language: found[1] } })],
        [/^(---|\*\*\*|___)$/, () => ({ type: 'delimiter', options: {} })],
    ];

    for (const [pattern, result] of rules) {
        const found = pattern.exec(typed);

        if (null !== found) {
            return result(found);
        }
    }

    return null;
}
