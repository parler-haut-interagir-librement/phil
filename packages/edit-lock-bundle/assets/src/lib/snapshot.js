// What the editor holds, block by block, so that the blocks a writer changed are told apart: each block by its own
// fields and the identifiers of the blocks it holds, its list and the block before it.

/** The blocks of a document's JSON; none when it cannot be read. */
export function parseBlocks(json) {
    try {
        const value = JSON.parse(json);

        return null !== value && 'object' === typeof value && Array.isArray(value.blocks) ? value.blocks : [];
    } catch {
        return [];
    }
}

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

/** The lists a block holds, keyed as the block editor keys them: its id, or its id and a column's index. */
export function childLists(block) {
    const lists = [];

    if (Array.isArray(block.data.blocks)) {
        lists.push({ key: block.id, list: block.data.blocks });
    }

    if (Array.isArray(block.data.cols)) {
        block.data.cols.forEach((column, index) => {
            lists.push({ key: block.id + ':' + index, list: Array.isArray(column?.blocks) ? column.blocks : [] });
        });
    }

    return lists;
}

/** Every block by its identifier: `{block, list, after, shape}`. */
export function snapshot(blocks) {
    const blocksById = new Map();
    const visit = (list, key) => {
        let after = null;

        for (const block of list) {
            if (!isBlock(block)) {
                continue;
            }

            blocksById.set(block.id, { block, list: key, after, shape: shape(block) });
            after = block.id;
            childLists(block).forEach((child) => {
                visit(child.list, child.key);
            });
        }
    };

    visit(blocks, 'root');

    return blocksById;
}

/** A block's own fields, the blocks it holds by their identifiers only. */
export function shape(block) {
    const data = { ...block.data };
    const ids = (list) => (Array.isArray(list) ? list.filter(isBlock).map((child) => child.id) : []);

    if (Array.isArray(data.blocks)) {
        data.blocks = ids(data.blocks);
    }

    if (Array.isArray(data.cols)) {
        data.cols = data.cols.map((column) => ({ ...column, blocks: ids(column?.blocks) }));
    }

    return JSON.stringify([block.type, data, block.tunes ?? null]);
}

/** The blocks added or changed, in place or in content, and those gone, from one snapshot to the next. */
export function changes(before, after) {
    const saved = [];
    const removed = [];

    for (const [id, entry] of after) {
        const previous = before.get(id);

        if (
            undefined === previous ||
            previous.shape !== entry.shape ||
            previous.list !== entry.list ||
            previous.after !== entry.after
        ) {
            saved.push(id);
        }
    }

    for (const id of before.keys()) {
        if (!after.has(id)) {
            removed.push(id);
        }
    }

    return { saved, removed };
}
