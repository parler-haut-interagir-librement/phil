// The document the editor holds: UJO read and written, blocks found, inserted, moved, duplicated and removed in
// nested lists, list items and table cells changed, and an undo history. Pure functions, without the DOM.

const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';

/** The version of UJO the editor writes, when the document it read had none. */
export const VERSION = '2.31.7';

/** A random identifier of ten characters, as Editor.js gives each block. */
export function newId(random = (bytes) => globalThis.crypto.getRandomValues(bytes)) {
    return Array.from(random(new Uint8Array(10)), (byte) => ALPHABET[byte & 63]).join('');
}

/**
 * The document of UJO JSON: `{time, version, blocks}`. Empty, invalid or foreign JSON gives an empty document — the
 * server is the judge of what it receives; the editor never refuses to open.
 */
export function parseDocument(json) {
    let value = null;

    try {
        value = '' === (json ?? '').trim() ? null : JSON.parse(json);
    } catch {
        value = null;
    }

    const valid = null !== value && 'object' === typeof value && Array.isArray(value.blocks);

    return {
        time: valid && Number.isInteger(value.time) ? value.time : null,
        version: valid && 'string' === typeof value.version ? value.version : VERSION,
        blocks: valid ? value.blocks.filter(isBlock) : [],
    };
}

/** Whether a value has the shape of a block: a text identifier and type, and data that is an object. */
export function isBlock(block) {
    return (
        null !== block &&
        'object' === typeof block &&
        'string' === typeof block.id &&
        'string' === typeof block.type &&
        null !== block.data &&
        'object' === typeof block.data &&
        !Array.isArray(block.data)
    );
}

/**
 * The UJO JSON of a document, its empty blocks left out by `isEmpty(block)`; the empty text when no block is left,
 * so that a required field stays empty.
 */
export function serialize(document, isEmpty, now = Date.now()) {
    const blocks = prune(document.blocks, isEmpty);

    return 0 === blocks.length ? '' : JSON.stringify({ time: now, blocks, version: document.version ?? VERSION });
}

function prune(blocks, isEmpty) {
    const kept = [];

    for (const block of blocks) {
        const copy = { ...block, data: { ...block.data } };

        if (Array.isArray(copy.data.blocks)) {
            copy.data.blocks = prune(copy.data.blocks, isEmpty);
        }

        if (Array.isArray(copy.data.cols)) {
            copy.data.cols = copy.data.cols.map((column) => ({
                ...column,
                blocks: prune(column.blocks ?? [], isEmpty),
            }));
        }

        if (!isEmpty(copy)) {
            kept.push(copy);
        }
    }

    return kept;
}

/**
 * The lists of blocks nested in a block, each with its key: `id` for `data.blocks` (a toggle, a note), `id:n` for the
 * blocks of column `n`.
 */
export function childLists(block) {
    const lists = [];

    if (Array.isArray(block.data.blocks)) {
        lists.push({ key: block.id, list: block.data.blocks });
    }

    (Array.isArray(block.data.cols) ? block.data.cols : []).forEach((column, index) => {
        column.blocks ??= [];
        lists.push({ key: block.id + ':' + index, list: column.blocks });
    });

    return lists;
}

/** Where a block is: its list, its index, the key of its list (`root` at the top); null when nowhere. */
export function locate(blocks, id, key = 'root') {
    for (const [index, block] of blocks.entries()) {
        if (block.id === id) {
            return { list: blocks, index, block, key };
        }

        for (const child of childLists(block)) {
            const found = locate(child.list, id, child.key);

            if (null !== found) {
                return found;
            }
        }
    }

    return null;
}

/** The list of blocks a key names, `root` or a nested list's key; null when none. */
export function listOf(blocks, key) {
    if ('root' === key) {
        return blocks;
    }

    for (const block of walk(blocks)) {
        const found = childLists(block).find((child) => child.key === key);

        if (undefined !== found) {
            return found.list;
        }
    }

    return null;
}

/**
 * Puts a block another writer saved into the document: in place of the block of the same identifier, wherever it is;
 * otherwise in the list of the key, after the block named, first when none is named, last when that block is not in
 * the list; at the end of the document when the list is nowhere. Where it went: `{replaced, key, index}`.
 */
export function place(blocks, block, key = 'root', after = null) {
    const found = locate(blocks, block.id);

    if (null !== found) {
        found.list[found.index] = block;

        return { replaced: true, key: found.key, index: found.index };
    }

    const list = listOf(blocks, key);

    if (null === list) {
        blocks.push(block);

        return { replaced: false, key: 'root', index: blocks.length - 1 };
    }

    const index = null === after ? 0 : list.findIndex((sibling) => sibling.id === after) + 1 || list.length;
    list.splice(index, 0, block);

    return { replaced: false, key, index };
}

/** Every block, nested ones included, in reading order. */
export function* walk(blocks) {
    for (const block of blocks) {
        yield block;

        for (const child of childLists(block)) {
            yield* walk(child.list);
        }
    }
}

/** Whether a block holds another, at any depth, or is it. */
export function contains(block, id) {
    return block.id === id || childLists(block).some((child) => child.list.some((inner) => contains(inner, id)));
}

/** Removes a block; what was removed and where, or null. */
export function removeBlock(blocks, id) {
    const found = locate(blocks, id);

    if (null !== found) {
        found.list.splice(found.index, 1);
    }

    return found;
}

/** Moves a block up (`-1`) or down (`1`) in its own list; whether it moved. */
export function moveBy(blocks, id, delta) {
    const found = locate(blocks, id);
    const target = null === found ? -1 : found.index + delta;

    if (target < 0 || target >= found.list.length) {
        return false;
    }

    found.list.splice(found.index, 1);
    found.list.splice(target, 0, found.block);

    return true;
}

/**
 * Moves a block into a list, at an index counted before the move; never into itself or a block it holds. Whether it
 * moved.
 */
export function moveTo(blocks, id, key, index) {
    const found = locate(blocks, id);
    const list = listOf(blocks, key);

    if (null === found || null === list || ('root' !== key && contains(found.block, key.split(':')[0]))) {
        return false;
    }

    const at = list === found.list && index > found.index ? index - 1 : index;
    found.list.splice(found.index, 1);
    list.splice(Math.max(0, Math.min(at, list.length)), 0, found.block);

    return true;
}

/** A deep copy of a block, it and every nested block under new identifiers. */
export function cloneBlock(block, makeId = newId) {
    const copy = JSON.parse(JSON.stringify(block));
    copy.id = makeId();

    for (const child of childLists(copy)) {
        child.list.splice(0, child.list.length, ...child.list.map((inner) => cloneBlock(inner, makeId)));
    }

    return copy;
}

/** Puts a copy of a block right after it; the copy, or null. */
export function duplicate(blocks, id, makeId = newId) {
    const found = locate(blocks, id);

    if (null === found) {
        return null;
    }

    const copy = cloneBlock(found.block, makeId);
    found.list.splice(found.index + 1, 0, copy);

    return copy;
}

// List items: a path of indexes from the list's items to an item.

/** The item at a path, or undefined. */
export function itemAt(items, path) {
    let item;
    let level = items;

    for (const index of path) {
        item = level?.[index];
        level = item?.items;
    }

    return item;
}

function levelOf(items, path) {
    return 1 === path.length ? items : itemAt(items, path.slice(0, -1)).items;
}

/** A new item of a list's style. */
export function newItem(style, content = '') {
    return { content, meta: 'checklist' === style ? { checked: false } : {}, items: [] };
}

/**
 * Splits an item at the caret: the item keeps what was before it, a new item after it gets what followed, and the
 * nested items move to the new item. The new item's path.
 */
export function splitItem(data, path, before, after) {
    const item = itemAt(data.items, path);
    const next = newItem(data.style, after);
    next.items = item.items;
    item.content = before;
    item.items = [];
    levelOf(data.items, path).splice(path.at(-1) + 1, 0, next);

    return [...path.slice(0, -1), path.at(-1) + 1];
}

/** Nests an item under the one before it; its new path, or null for the first item of its level. */
export function indentItem(data, path) {
    const index = path.at(-1);

    if (0 === index) {
        return null;
    }

    const level = levelOf(data.items, path);
    const [item] = level.splice(index, 1);
    const parent = level[index - 1];
    parent.items.push(item);

    return [...path.slice(0, -1), index - 1, parent.items.length - 1];
}

/**
 * Moves an item out to its parent's level, right after its parent, the items after it becoming its own; its new
 * path, or null at the top level.
 */
export function outdentItem(data, path) {
    if (1 === path.length) {
        return null;
    }

    const parentPath = path.slice(0, -1);
    const parent = itemAt(data.items, parentPath);
    const [item, ...rest] = parent.items.splice(path.at(-1));
    item.items.push(...rest);
    levelOf(data.items, parentPath).splice(parentPath.at(-1) + 1, 0, item);

    return [...parentPath.slice(0, -1), parentPath.at(-1) + 1];
}

/**
 * Removes an item, its nested items taking its place; the path of the item before it in reading order, or null when
 * it was the first.
 */
export function removeItem(data, path) {
    const flat = flatItems(data.items);
    const position = flat.findIndex((entry) => entry.path.join('.') === path.join('.'));
    levelOf(data.items, path).splice(path.at(-1), 1, ...itemAt(data.items, path).items);

    // What comes before an item in reading order sits before it in the tree: its path is unchanged by the removal.
    return 0 === position ? null : flat[position - 1].path;
}

/** Every item with its path, in reading order. */
export function flatItems(items, prefix = []) {
    return items.flatMap((item, index) => [
        { path: [...prefix, index], item },
        ...flatItems(item.items ?? [], [...prefix, index]),
    ]);
}

/** The items of a list as their contents, in reading order. */
export function itemContents(items) {
    return flatItems(items).map((entry) => entry.item.content);
}

/** A list's data in another style: the items kept, their checked state set or dropped. */
export function restyleList(data, style) {
    const restyle = (items) =>
        items.map((item) => ({
            content: item.content,
            meta: 'checklist' === style ? { checked: true === item.meta?.checked } : {},
            items: restyle(item.items ?? []),
        }));

    return { style, meta: {}, items: restyle(data.items) };
}

// Tables: rows of cells, every row as long as the first.

export function addRow(data, at = data.content.length) {
    const width = data.content[0]?.length ?? 1;
    data.content.splice(
        at,
        0,
        Array.from({ length: width }, () => ''),
    );

    return data;
}

export function addColumn(data, at = data.content[0]?.length ?? 0) {
    data.content.forEach((row) => {
        row.splice(at, 0, '');
    });

    return data;
}

export function removeRow(data, at = data.content.length - 1) {
    if (data.content.length > 1) {
        data.content.splice(at, 1);
    }

    return data;
}

export function removeColumn(data, at = (data.content[0]?.length ?? 1) - 1) {
    if ((data.content[0]?.length ?? 0) > 1) {
        data.content.forEach((row) => {
            row.splice(at, 1);
        });
    }

    return data;
}

/**
 * The states to go back and forth to. `record()` keeps the state before a change, and clears what was undone; a
 * state equal to the last one kept is not kept twice.
 */
export class History {
    constructor(limit = 100) {
        this.limit = limit;
        this.past = [];
        this.future = [];
    }

    record(state) {
        if (this.past.at(-1) !== state) {
            this.past.push(state);
            this.past.splice(0, Math.max(0, this.past.length - this.limit));
        }

        this.future = [];
    }

    undo(current) {
        return this.#step(this.past, this.future, current);
    }

    redo(current) {
        return this.#step(this.future, this.past, current);
    }

    #step(from, to, current) {
        let state = from.pop();

        while (state === current) {
            state = from.pop();
        }

        if (undefined === state) {
            return null;
        }

        to.push(current);

        return state;
    }
}
