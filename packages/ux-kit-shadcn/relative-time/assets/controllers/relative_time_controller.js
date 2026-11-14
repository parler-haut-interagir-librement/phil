import { Controller } from '@hotwired/stimulus';

const SECOND = 1000;

const UNITS = [
    ['year', 365 * 86400 * SECOND],
    ['month', 30 * 86400 * SECOND],
    ['week', 7 * 86400 * SECOND],
    ['day', 86400 * SECOND],
    ['hour', 3600 * SECOND],
    ['minute', 60 * SECOND],
    ['second', SECOND],
];

/** The longest a timer waits, the words being right to the day beyond it. */
const LONGEST = 3600 * SECOND;

/**
 * What `then` is from `now`, in milliseconds: the number and the unit to write, and how long the words stay right.
 */
export function relative(then, now) {
    const delta = then - now;
    const [unit, size] = UNITS.find(([, length]) => Math.abs(delta) >= length) ?? UNITS[UNITS.length - 1];
    const left = size - (Math.abs(delta) % size);

    return { value: Math.trunc(delta / size), unit, next: Math.max(SECOND, Math.min(LONGEST, left)) };
}

/*
 * The time from now of a `time` element, kept right by one timer, set again when the page comes back. Past the limit
 * and for what cannot be read, the date of the server stays.
 */
export default class extends Controller {
    static values = {
        locale: String,
        length: { type: String, default: 'long' },
        limit: { type: Number, default: 0 },
    };

    #timer = null;
    #visible = () => this.#write();

    connect() {
        document.addEventListener('visibilitychange', this.#visible);
        this.#write();
    }

    disconnect() {
        clearTimeout(this.#timer);
        document.removeEventListener('visibilitychange', this.#visible);
    }

    #write() {
        clearTimeout(this.#timer);

        const then = Date.parse(this.element.getAttribute('datetime'));
        const now = Date.now();

        if (Number.isNaN(then) || (this.limitValue > 0 && Math.abs(then - now) > this.limitValue * SECOND)) {
            return;
        }

        const locale = this.localeValue || document.documentElement.lang || undefined;
        const { value, unit, next } = relative(then, now);
        const words = new Intl.RelativeTimeFormat(locale, { numeric: 'auto', style: this.lengthValue });

        this.element.textContent = words.format(value, unit);
        this.#timer = setTimeout(() => this.#write(), next);
    }
}
