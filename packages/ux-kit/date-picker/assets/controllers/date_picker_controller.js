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

/** The first day of the month of a time. */
export const monthOf = (time) => {
    const date = new Date(time);

    return Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1);
};

/**
 * The first month shown so that a time stays in view: `first` kept while the time is within its `count` months, moved
 * otherwise just enough to bring it back, on the left when it went before, on the right when it went past.
 */
export function shownFrom(first, time, count) {
    const month = monthOf(time);

    if (month < first) {
        return month;
    }

    return month > addMonths(first, count - 1) ? addMonths(month, 1 - count) : first;
}

/**
 * The range from a start and an end, in order: the earlier is the start whichever was chosen first; null without a
 * start.
 */
export function ordered(start, end) {
    if (start === null) {
        return null;
    }

    if (end === null) {
        return { start, end: start };
    }

    return start > end ? { start: end, end: start } : { start, end };
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
 *
 * With an `end` target, a range on as many months as there are `weeks` targets: the first day chosen is the start, the
 * second the end, the two swapped when the end comes first; until then the days between the start and the day under
 * the pointer or the focus are previewed. The field of the end never takes a date before that of the start.
 */
export default class extends Controller {
    static targets = ['input', 'end', 'trigger', 'panel', 'heading', 'weekdays', 'weeks', 'status'];

    static classes = ['day', 'weekday'];

    static values = {
        locale: String,
        firstDay: { type: Number, default: -1 },
        rangeStartLabel: { type: String, default: 'start of the range' },
        rangeEndLabel: { type: String, default: 'end of the range' },
    };

    #focused = 0;
    #first = 0;
    #pending = null;
    #preview = null;
    #buttons = [];
    #follow = () => this.#place();

    connect() {
        this.triggerTarget.hidden = !('popover' in HTMLElement.prototype);
        this.limit();
    }

    disconnect() {
        this.#stopFollowing();
    }

    /** The end of a range starts where its start is, or at the first date allowed. */
    limit() {
        if (!this.hasEndTarget) {
            return;
        }

        const start = this.inputTarget.value || this.inputTarget.min;

        if (start) {
            this.endTarget.min = start;
        } else {
            this.endTarget.removeAttribute('min');
        }
    }

    toggled(event) {
        const open = event.newState === 'open';
        this.triggerTarget.setAttribute('aria-expanded', String(open));
        this.#pending = null;
        this.#preview = null;

        if (!open) {
            this.#stopFollowing();

            return;
        }

        this.#focused = this.#clamp(parse(this.inputTarget.value) ?? this.#today());
        this.#first = monthOf(this.#focused);
        this.#announce('');
        this.#render();
        this.#place();
        this.#day(this.#focused)?.focus();
        window.addEventListener('scroll', this.#follow, { passive: true, capture: true });
        window.addEventListener('resize', this.#follow, { passive: true });
    }

    previous() {
        this.#turn(-1);
    }

    next() {
        this.#turn(1);
    }

    choose(event) {
        const day = event.target.closest('[data-date]');

        if (!day || day.disabled) {
            return;
        }

        if (!this.hasEndTarget) {
            this.#write(this.inputTarget, day.dataset.date);
            this.#close();

            return;
        }

        const time = parse(day.dataset.date);

        if (this.#pending === null) {
            this.#pending = time;
            this.#preview = time;
            this.#focused = time;
            this.#mark();
            this.#announce(`${this.#formatter({ dateStyle: 'full' }).format(time)}, ${this.rangeStartLabelValue}`);

            return;
        }

        const { start, end } = ordered(this.#pending, time);
        this.#write(this.inputTarget, iso(start));
        this.limit();
        this.#write(this.endTarget, iso(end));
        this.#close();
    }

    hover(event) {
        const day = event.target.closest('[data-date]');

        if (this.#pending === null || !day || day.disabled) {
            return;
        }

        this.#preview = parse(day.dataset.date);
        this.#mark();
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
        this.#preview = this.#focused;
        const first = shownFrom(this.#first, this.#focused, this.weeksTargets.length);

        if (first === this.#first) {
            this.#mark();
        } else {
            this.#first = first;
            this.#render();
        }

        this.#day(this.#focused)?.focus();
    }

    #turn(count) {
        this.#first = addMonths(this.#first, count);
        this.#focused = this.#clamp(addMonths(this.#focused, count));
        this.#first = shownFrom(this.#first, this.#focused, this.weeksTargets.length);
        this.#render();
    }

    #write(input, value) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    #close() {
        this.panelTarget.hidePopover();
        this.triggerTarget.focus();
    }

    #announce(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
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
        const short = this.#formatter({ weekday: 'short' });
        const full = this.#formatter({ weekday: 'long' });
        const number = this.#formatter({ day: 'numeric' });
        const today = iso(this.#today());
        const firstDay = this.#firstDay();
        const single = this.weeksTargets.length === 1;
        this.#buttons = [];

        this.weeksTargets.forEach((target, index) => {
            const shown = addMonths(this.#first, index);
            const rows = weeks(shown, firstDay);
            const prefix = iso(shown).slice(0, 7);

            this.headingTargets[index].textContent = month.format(shown);
            this.weekdaysTargets[index].replaceChildren(
                ...rows[0].map((time) => {
                    const cell = document.createElement('th');
                    cell.scope = 'col';
                    cell.abbr = full.format(time);
                    cell.textContent = short.format(time);
                    cell.classList.add(...this.weekdayClasses);

                    return cell;
                }),
            );
            target.replaceChildren(
                ...rows.map((week) => {
                    const row = document.createElement('tr');
                    row.append(
                        ...week.map((time) =>
                            this.#cell(time, { inside: iso(time).startsWith(prefix), single, number, today }),
                        ),
                    );

                    return row;
                }),
            );
        });

        this.#mark();
    }

    /** A day of the month, or one of the next or the last shown muted; left empty beside another month. */
    #cell(time, { inside, single, number, today }) {
        const cell = document.createElement('td');
        cell.setAttribute('role', 'gridcell');

        if (!inside && !single) {
            return cell;
        }

        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.date = iso(time);
        button.textContent = number.format(time);
        button.disabled = !this.#allowed(time);
        button.classList.add(...this.dayClasses);

        if (button.dataset.date === today) {
            button.setAttribute('aria-current', 'date');
        }

        if (!inside) {
            button.dataset.outside = 'true';
        }

        cell.append(button);
        this.#buttons.push({ time, button, cell });

        return cell;
    }

    /** The focus, the name and the state of each day, kept on the buttons as they are, for the pointer under them. */
    #mark() {
        const long = this.#formatter({ dateStyle: 'full' });
        const range = this.#range();
        const selected = this.inputTarget.value;
        const words = { start: this.rangeStartLabelValue, end: this.rangeEndLabelValue };

        for (const { time, button, cell } of this.#buttons) {
            const name = long.format(time);
            button.tabIndex = time === this.#focused ? 0 : -1;

            if (range === undefined) {
                button.setAttribute('aria-label', name);
                button.setAttribute('aria-pressed', String(button.dataset.date === selected));

                continue;
            }

            const edge = this.#edge(time, range);
            cell.setAttribute('aria-selected', String(edge !== null));
            button.setAttribute('aria-label', edge === null || edge === 'within' ? name : `${name}, ${words[edge]}`);

            if (edge === null) {
                delete button.dataset.range;
            } else {
                button.dataset.range = edge;
            }
        }
    }

    /** Where a day stands in a range: `start`, `end`, `within`, or null out of it. */
    #edge(time, range) {
        if (range === null || time < range.start || time > range.end) {
            return null;
        }

        if (time === range.start) {
            return 'start';
        }

        return time === range.end ? 'end' : 'within';
    }

    /** The range shown: undefined for a single date, null when nothing is chosen. */
    #range() {
        if (!this.hasEndTarget) {
            return undefined;
        }

        if (this.#pending !== null) {
            return ordered(this.#pending, this.#preview ?? this.#pending);
        }

        return ordered(parse(this.inputTarget.value), parse(this.endTarget.value));
    }

    #day(time) {
        const date = iso(time);

        return this.#buttons.find(({ button }) => button.dataset.date === date)?.button ?? null;
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
