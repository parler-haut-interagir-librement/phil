// Who edits what: the leases of a presence stream, each with the moment it ends on this page's clock, turned into
// the blocks to close and the roster to show.

/** Each item `{holder, name, part, expiresIn}` with `deadline`, in milliseconds of this page's clock. */
export function withDeadlines(items, now) {
    return items.map((item) => ({ ...item, deadline: now + Math.max(0, Number(item.expiresIn) || 0) * 1000 }));
}

export function active(items, now) {
    return items.filter((item) => item.deadline > now);
}

/** The blocks other writers hold, by identifier, with the name of their holder; a whole lease holds them all. */
export function locks(items, me, blockIds) {
    const held = {};

    for (const item of items) {
        if (item.holder === me) {
            continue;
        }

        for (const id of '' === item.part ? blockIds : [item.part]) {
            held[id] ??= item.name;
        }
    }

    return held;
}

/** Whether two sets of locks are the same. */
export function sameLocks(one, other) {
    const keys = Object.keys(one);

    return keys.length === Object.keys(other).length && keys.every((key) => one[key] === other[key]);
}

/** The writers, in the order they appear: `{holder, name, parts, whole, mine}`. */
export function roster(items, me) {
    const writers = new Map();

    for (const item of items) {
        const writer = writers.get(item.holder) ?? {
            holder: item.holder,
            name: item.name,
            parts: [],
            whole: false,
            mine: item.holder === me,
        };

        if ('' === item.part) {
            writer.whole = true;
        } else {
            writer.parts.push(item.part);
        }

        writers.set(item.holder, writer);
    }

    return [...writers.values()];
}
