import { Controller } from '@hotwired/stimulus';

/**
 * Where a box goes against its trigger: on the side asked for, or on the other one when it does not fit there and the
 * other has more room; aligned along the cross axis, `start` and `end` following the reading direction, and pushed
 * back inside the viewport. Every value is in viewport pixels, as `getBoundingClientRect()` gives them.
 */
export function placement(trigger, box, viewport, { side = 'bottom', align = 'center', gap = 6, rtl = false } = {}) {
    const vertical = side === 'top' || side === 'bottom';
    const size = vertical ? box.height : box.width;
    const roomBefore = (vertical ? trigger.top : trigger.left) - gap;
    const roomAfter = (vertical ? viewport.height - trigger.bottom : viewport.width - trigger.right) - gap;
    const wantsBefore = side === 'top' || side === 'left';
    const flipped = wantsBefore
        ? size > roomBefore && roomAfter > roomBefore
        : size > roomAfter && roomBefore > roomAfter;
    const before = wantsBefore !== flipped;
    const main = before
        ? (vertical ? trigger.top : trigger.left) - gap - size
        : (vertical ? trigger.bottom : trigger.right) + gap;

    const crossStart = vertical ? trigger.left : trigger.top;
    const crossEnd = vertical ? trigger.right : trigger.bottom;
    const crossSize = vertical ? box.width : box.height;
    const edge = vertical && rtl ? ({ start: 'end', end: 'start' }[align] ?? align) : align;
    const cross =
        edge === 'start' ? crossStart : edge === 'end' ? crossEnd - crossSize : (crossStart + crossEnd - crossSize) / 2;
    const limit = (vertical ? viewport.width : viewport.height) - crossSize;
    const clamped = Math.min(Math.max(0, cross), Math.max(0, limit));
    const names = vertical ? ['top', 'bottom'] : ['left', 'right'];

    return {
        top: Math.round(vertical ? main : clamped),
        left: Math.round(vertical ? clamped : main),
        side: names[before ? 0 : 1],
    };
}

const DAY = 86400000;
const WEEK = 7;
const CELLS = 42;

/** A date as `YYYY-MM-DD`, from a time of day at midnight UTC: no shift of the clock ever moves a day. */
export const iso = (time) => new Date(time).toISOString().slice(0, 10);

/** The time at midnight UTC of a `YYYY-MM-DD`; null for anything else. */
export function parse(text) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(text ?? '')) {
        return null;
    }

    const time = Date.parse(`${text}T00:00:00Z`);

    return Number.isNaN(time) ? null : time;
}

/** The six weeks a month is shown on, as times, starting on `firstDay` (0 for Sunday). */
export function weeks(time, firstDay) {
    const date = new Date(time);
    const first = Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1);
    const back = (new Date(first).getUTCDay() - firstDay + WEEK) % WEEK;
    const start = first - back * DAY;
    const rows = [];

    for (let cell = 0; cell < CELLS; cell += WEEK) {
        rows.push(Array.from({ length: WEEK }, (_, day) => start + (cell + day) * DAY));
    }

    return rows;
}

/** The same day some months away, or the last of its month when that month is shorter. */
export function addMonths(time, count) {
    const date = new Date(time);
    const last = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + count + 1, 0)).getUTCDate();

    return Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + count, Math.min(date.getUTCDate(), last));
}

/*
 * A month on a grid, in a popover opened by a button beside the date field: the date picker dialog of WAI-ARIA. The
 * arrows move by day and week, mirrored right to left, Home and End to the ends of the week, Page Up and Page Down by
 * month, and by year with Shift. Enter and the Space bar are those of the button of a day; the browser closes on
 * Escape and gives the focus back.
 */
export default class extends Controller {
    static targets = ['input', 'trigger', 'panel', 'heading', 'weekdays', 'weeks'];

    static classes = ['day', 'weekday'];

    static values = {
        locale: String,
        firstDay: { type: Number, default: -1 },
    };

    #focused = 0;
    #follow = () => this.#place();

    connect() {
        this.triggerTarget.hidden = !('popover' in HTMLElement.prototype);
    }

    disconnect() {
        this.#stopFollowing();
    }

    toggled(event) {
        const open = event.newState === 'open';
        this.triggerTarget.setAttribute('aria-expanded', String(open));

        if (!open) {
            this.#stopFollowing();

            return;
        }

        this.#focused = this.#clamp(parse(this.inputTarget.value) ?? this.#today());
        this.#render();
        this.#place();
        this.#day(this.#focused)?.focus();
        window.addEventListener('scroll', this.#follow, { passive: true, capture: true });
        window.addEventListener('resize', this.#follow, { passive: true });
    }

    previous() {
        this.#focused = this.#clamp(addMonths(this.#focused, -1));
        this.#render();
    }

    next() {
        this.#focused = this.#clamp(addMonths(this.#focused, 1));
        this.#render();
    }

    choose(event) {
        const day = event.target.closest('[data-date]');

        if (!day || day.disabled) {
            return;
        }

        const input = this.inputTarget;
        input.value = day.dataset.date;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        this.panelTarget.hidePopover();
        this.triggerTarget.focus();
    }

    navigate(event) {
        if (!event.target.closest('[data-date]')) {
            return;
        }

        const target = this.#destination(event);

        if (target === null) {
            return;
        }

        event.preventDefault();
        this.#focused = this.#clamp(target);
        this.#render();
        this.#day(this.#focused)?.focus();
    }

    #destination({ key, shiftKey }) {
        const rtl = getComputedStyle(this.element).direction === 'rtl';
        const weekday = (new Date(this.#focused).getUTCDay() - this.#firstDay() + WEEK) % WEEK;
        const moves = {
            ArrowLeft: () => this.#focused + (rtl ? DAY : -DAY),
            ArrowRight: () => this.#focused + (rtl ? -DAY : DAY),
            ArrowUp: () => this.#focused - WEEK * DAY,
            ArrowDown: () => this.#focused + WEEK * DAY,
            Home: () => this.#focused - weekday * DAY,
            End: () => this.#focused + (WEEK - 1 - weekday) * DAY,
            PageUp: () => addMonths(this.#focused, shiftKey ? -12 : -1),
            PageDown: () => addMonths(this.#focused, shiftKey ? 12 : 1),
        };

        return moves[key]?.() ?? null;
    }

    #render() {
        const month = this.#formatter({ month: 'long', year: 'numeric' });
        const long = this.#formatter({ dateStyle: 'full' });
        const number = this.#formatter({ day: 'numeric' });
        const short = this.#formatter({ weekday: 'short' });
        const full = this.#formatter({ weekday: 'long' });
        const rows = weeks(this.#focused, this.#firstDay());
        const shown = iso(this.#focused).slice(0, 7);
        const selected = this.inputTarget.value;
        const today = iso(this.#today());

        this.headingTarget.textContent = month.format(this.#focused);
        this.weekdaysTarget.replaceChildren(
            ...rows[0].map((time) => {
                const cell = document.createElement('th');
                cell.scope = 'col';
                cell.abbr = full.format(time);
                cell.textContent = short.format(time);
                cell.classList.add(...this.weekdayClasses);

                return cell;
            }),
        );
        this.weeksTarget.replaceChildren(
            ...rows.map((week) => {
                const row = document.createElement('tr');
                row.append(...week.map((time) => this.#cell(time, { long, number, shown, selected, today })));

                return row;
            }),
        );
    }

    #cell(time, { long, number, shown, selected, today }) {
        const date = iso(time);
        const cell = document.createElement('td');
        const button = document.createElement('button');

        button.type = 'button';
        button.dataset.date = date;
        button.textContent = number.format(time);
        button.tabIndex = time === this.#focused ? 0 : -1;
        button.disabled = !this.#allowed(time);
        button.classList.add(...this.dayClasses);
        button.setAttribute('aria-label', long.format(time));
        button.setAttribute('aria-pressed', String(date === selected));

        if (date === today) {
            button.setAttribute('aria-current', 'date');
        }

        if (!date.startsWith(shown)) {
            button.dataset.outside = 'true';
        }

        cell.setAttribute('role', 'gridcell');
        cell.append(button);

        return cell;
    }

    #day(time) {
        return this.weeksTarget.querySelector(`[data-date="${iso(time)}"]`);
    }

    #place() {
        const panel = this.panelTarget;
        const { top, left } = placement(
            this.triggerTarget.getBoundingClientRect(),
            { width: panel.offsetWidth, height: panel.offsetHeight },
            { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
            { align: 'end', rtl: getComputedStyle(panel).direction === 'rtl' },
        );

        panel.style.inset = `${top}px auto auto ${left}px`;
    }

    #stopFollowing() {
        window.removeEventListener('scroll', this.#follow, { capture: true });
        window.removeEventListener('resize', this.#follow);
    }

    #clamp(time) {
        return Math.min(
            Math.max(time, parse(this.inputTarget.min) ?? Number.NEGATIVE_INFINITY),
            parse(this.inputTarget.max) ?? Number.POSITIVE_INFINITY,
        );
    }

    #allowed(time) {
        const min = parse(this.inputTarget.min);
        const max = parse(this.inputTarget.max);

        return (min === null || time >= min) && (max === null || time <= max);
    }

    #today() {
        const now = new Date();

        return Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
    }

    #locale() {
        return this.localeValue || document.documentElement.lang || undefined;
    }

    #firstDay() {
        if (this.firstDayValue >= 0) {
            return this.firstDayValue % WEEK;
        }

        const locale = new Intl.Locale(this.#locale() ?? navigator.language);

        return ((locale.getWeekInfo?.() ?? locale.weekInfo)?.firstDay ?? 1) % WEEK;
    }

    #formatter(options) {
        return new Intl.DateTimeFormat(this.#locale(), { ...options, timeZone: 'UTC' });
    }
}
