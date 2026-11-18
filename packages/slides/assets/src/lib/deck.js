/*
 * What the two controllers of phil/slides share, without the DOM: the position in a deck — the slide, and how many of
 * its fragments are shown —, the keys that move it, the messages the windows of a deck exchange through their
 * BroadcastChannel, and the texts of the counter, the live region and the timer.
 */

/** The prefix of the BroadcastChannel of a deck, followed by its identifier. */
export const CHANNEL_PREFIX = 'phil-slides:';

const KEYS = {
    ArrowRight: 'next',
    ArrowDown: 'next',
    PageDown: 'next',
    ' ': 'next',
    ArrowLeft: 'previous',
    ArrowUp: 'previous',
    PageUp: 'previous',
    Home: 'first',
    End: 'last',
    Escape: 'exit',
    f: 'fullscreen',
    F: 'fullscreen',
};

const EDITABLE = ['INPUT', 'TEXTAREA', 'SELECT'];

/**
 * The command of a key: `next`, `previous`, `first`, `last`, `exit` or `fullscreen`; `null` for a key the deck leaves
 * alone — one with a modifier, one typed in a field, one another handler already took.
 */
export function command(event) {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
        return null;
    }

    const target = event.target;

    if (target && (EDITABLE.includes(target.tagName) || target.isContentEditable)) {
        return null;
    }

    return Object.hasOwn(KEYS, event.key) ? KEYS[event.key] : null;
}

/**
 * Where a deck stands: the index of the current slide, from 0, and the number of its fragments shown.
 */
export class Position {
    constructor(fragments) {
        this.fragments = fragments;
        this.slide = 0;
        this.fragment = 0;
    }

    get count() {
        return this.fragments.length;
    }

    /** The next fragment of the slide, or the next slide; whether the position moved. */
    next() {
        if (this.fragment < (this.fragments[this.slide] ?? 0)) {
            return this.go(this.slide, this.fragment + 1);
        }

        return this.slide < this.count - 1 ? this.go(this.slide + 1, 0) : false;
    }

    /** The fragment before, or the slide before with every fragment shown; whether the position moved. */
    previous() {
        if (this.fragment > 0) {
            return this.go(this.slide, this.fragment - 1);
        }

        return this.slide > 0 ? this.go(this.slide - 1, this.fragments[this.slide - 1]) : false;
    }

    first() {
        return this.go(0, 0);
    }

    last() {
        return this.go(this.count - 1, this.fragments[this.count - 1] ?? 0);
    }

    /** A position, brought within the deck; whether it moved. */
    go(slide, fragment) {
        if (this.count === 0) {
            return false;
        }

        const index = clamp(Number.isInteger(slide) ? slide : 0, 0, this.count - 1);
        const shown = clamp(Number.isInteger(fragment) ? fragment : 0, 0, this.fragments[index]);
        const moved = index !== this.slide || shown !== this.fragment;
        this.slide = index;
        this.fragment = shown;

        return moved;
    }
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

/** The index of the slide an address names by its anchor, `#slide-3`; `null` for none of the deck. */
export function slideFromHash(hash, count) {
    const match = /^#slide-([1-9]\d{0,5})$/.exec(hash ?? '');

    if (!match) {
        return null;
    }

    const index = Number(match[1]) - 1;

    return index < count ? index : null;
}

/** A pattern with its `%name%` placeholders replaced. */
export function format(pattern, values) {
    return pattern.replace(/%(\w+)%/g, (whole, name) => (Object.hasOwn(values, name) ? String(values[name]) : whole));
}

/** A message about a position, from a window. */
export function go(position, sender) {
    return { type: 'go', slide: position.slide, fragment: position.fragment, sender };
}

/**
 * What a message asks: `hello` from a window that opened, `go` to a position; `null` for a message of no window of
 * the deck, or from this window itself.
 */
export function read(data, sender) {
    if (!data || typeof data !== 'object' || data.sender === sender || typeof data.sender !== 'string') {
        return null;
    }

    if (data.type === 'hello') {
        return { type: 'hello' };
    }

    return data.type === 'go' && Number.isInteger(data.slide) && Number.isInteger(data.fragment)
        ? { type: 'go', slide: data.slide, fragment: data.fragment }
        : null;
}

/** A duration in seconds as a clock reads it, `4:05` or `1:02:03`, with a minus sign once it is negative. */
export function clock(seconds) {
    const whole = Math.trunc(Math.abs(seconds));
    const hours = Math.trunc(whole / 3600);
    const minutes = Math.trunc((whole % 3600) / 60);
    const rest = String(whole % 60).padStart(2, '0');
    const sign = seconds <= -1 ? '-' : '';

    return hours > 0 ? `${sign}${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${sign}${minutes}:${rest}`;
}

/** The ISO 8601 duration of a number of seconds, for the `datetime` of a `<time>`. */
export function isoDuration(seconds) {
    return `PT${Math.trunc(Math.abs(seconds))}S`;
}
