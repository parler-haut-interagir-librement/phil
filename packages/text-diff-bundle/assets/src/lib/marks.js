// What the comparison controller decides without the DOM: the views, the marks each shows, the stops of the
// navigation, the step from one to the next, and the labels.

/** The views of a difference: both texts marked, the old text alone, the new text alone. */
export const MODES = ['inline', 'old', 'new'];

/** A view the controller knows, or the inline view. */
export function normalizeMode(mode) {
    return MODES.includes(mode) ? mode : 'inline';
}

/** Whether a view shows a mark: the old text its deletions, the new text its insertions, the inline view both. */
export function shows(mode, tag) {
    return 'inline' === mode || ('old' === mode ? 'del' : 'ins') === tag;
}

/**
 * The stops of the navigation, each a list of indexes of `marks`: one per mark the view shows, an insertion that
 * follows its deletion joined to it in the inline view, so that a replacement is one stop.
 *
 * @param {{tag: string, joinsPrevious: boolean}[]} marks in reading order
 */
export function stops(marks, mode) {
    const groups = [];

    marks.forEach((mark, index) => {
        if (!shows(mode, mark.tag)) {
            return;
        }

        if ('inline' === mode && mark.joinsPrevious && 'ins' === mark.tag && 'del' === marks[index - 1]?.tag) {
            groups[groups.length - 1].push(index);
        } else {
            groups.push([index]);
        }
    });

    return groups;
}

/**
 * The stop after (`delta` 1) or before (`delta` -1) `current`, around the ends; the first or the last when no stop is
 * current; -1 when there is none.
 */
export function step(current, count, delta) {
    if (0 === count) {
        return -1;
    }

    if (current < 0 || current >= count) {
        return delta > 0 ? 0 : count - 1;
    }

    return (current + delta + count) % count;
}

/** A label with its `{current}` and `{total}` replaced. */
export function label(template, current, total) {
    return template.replaceAll('{current}', String(current)).replaceAll('{total}', String(total));
}
