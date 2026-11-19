import { Controller } from '@hotwired/stimulus';

/**
 * The day a key moves to from `index` among the days of seven-day weeks: a day either way with the side arrows —
 * mirrored when the page reads right to left —, a week with the others, the start and the end of the week with Home
 * and End. The index may fall outside the grid: the period before or after. `null` for any other key.
 */
export function moved(key, index, rtl = false) {
    const weekStart = index - (index % 7);
    const side = rtl ? -1 : 1;

    const target = {
        ArrowRight: index + side,
        ArrowLeft: index - side,
        ArrowDown: index + 7,
        ArrowUp: index - 7,
        Home: weekStart,
        End: weekStart + 6,
    }[key];

    return target === undefined ? null : target;
}

/*
 * The month's days as one stop of the tab order: the arrows move between the days' links, Home and End to the ends of
 * the week, Page Up and Page Down — and an arrow past the first or the last day — to the period before or after.
 * Enter opens the day, as a link does. A key with Alt, Ctrl or Meta is the browser's.
 */
export default class extends Controller {
    static targets = ['day', 'previous', 'next'];

    connect() {
        const current = this.dayTargets.findIndex((day) => day.hasAttribute('data-current'));
        this.dayTargets.forEach((day, position) => {
            day.tabIndex = position === Math.max(current, 0) ? 0 : -1;
        });
        this.keys = (event) => this.move(event);
        this.element.addEventListener('keydown', this.keys);
    }

    disconnect() {
        this.element.removeEventListener('keydown', this.keys);
    }

    move(event) {
        const index = this.dayTargets.indexOf(event.target);

        if (index === -1 || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const away = { PageUp: 'previous', PageDown: 'next' }[event.key];
        const target = moved(event.key, index, this.isRtl());

        if (away === undefined && target === null) {
            return;
        }

        event.preventDefault();

        if (away !== undefined || target < 0 || target >= this.dayTargets.length) {
            const link =
                (away ?? (target < 0 ? 'previous' : 'next')) === 'previous'
                    ? this.previousTargets[0]
                    : this.nextTargets[0];
            link?.click();

            return;
        }

        this.dayTargets.forEach((day, position) => {
            day.tabIndex = position === target ? 0 : -1;
        });
        this.dayTargets[target].focus();
    }

    isRtl() {
        return typeof getComputedStyle === 'function' && getComputedStyle(this.element).direction === 'rtl';
    }
}
