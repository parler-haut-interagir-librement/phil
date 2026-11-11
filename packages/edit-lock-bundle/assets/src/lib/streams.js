// The Turbo Streams the bundle pushes, read into actions: `{kind: 'presence', items}`, `{kind: 'save', holder, block,
// list, after}` and `{kind: 'remove', holder, id}`. Anything else is left out.

import { isBlock, parseBlocks } from './snapshot.js';

export const PRESENCE = 'phil-edit-lock-presence-';

export const BLOCK = 'phil-edit-lock-block-';

/** The actions of the `<turbo-stream>` elements under a parsed root. */
export function readStreams(root) {
    const actions = [];

    for (const stream of root.querySelectorAll('turbo-stream')) {
        const action = stream.getAttribute('action');
        const target = stream.getAttribute('target') ?? '';
        const holder = stream.getAttribute('data-holder');
        const content = stream.querySelector('template')?.content ?? null;

        if ('update' === action && target.startsWith(PRESENCE)) {
            actions.push({ kind: 'presence', items: Array.from(content?.querySelectorAll('li') ?? [], item) });
        } else if ('replace' === action && target.startsWith(BLOCK)) {
            const element = content?.firstElementChild ?? null;
            const blocks = null === element ? [] : parseBlocks(element.getAttribute('data-ujo') ?? '');

            if (1 === blocks.length && isBlock(blocks[0]) && BLOCK + blocks[0].id === target) {
                actions.push({
                    kind: 'save',
                    holder,
                    block: blocks[0],
                    list: element.getAttribute('data-list') ?? 'root',
                    after: element.getAttribute('data-after'),
                });
            }
        } else if ('remove' === action && target.startsWith(BLOCK)) {
            actions.push({ kind: 'remove', holder, id: target.slice(BLOCK.length) });
        }
    }

    return actions;
}

function item(element) {
    return {
        holder: element.getAttribute('data-holder') ?? '',
        part: element.getAttribute('data-part') ?? '',
        expiresIn: Number(element.getAttribute('data-expires-in')) || 0,
        name: element.textContent ?? '',
    };
}
