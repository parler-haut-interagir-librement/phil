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

/** The class of each side, the left and the right being the start and the end of the reading direction. */
export function sideClasses(side, rtl) {
    const start = rtl ? 'right' : 'left';
    const end = rtl ? 'left' : 'right';

    return { isTop: side === 'top', isBottom: side === 'bottom', isStart: side === start, isEnd: side === end };
}

/** How long the tooltip stays once the pointer left, so that it can reach the tooltip (WCAG 1.4.13). */
export const GRACE = 100;

/** The room between the tooltip and its trigger, for the arrow. */
const ARROW = 8;

/*
 * A tooltip on a manual popover, in the top layer so that no overflow cuts it. It shows on hover and focus, after the
 * delay asked for, stays while the pointer is on it, and Escape dismisses it from anywhere in the page.
 */
export default class extends Controller {
    static values = {
        delayDuration: { type: Number, default: 0 },
        wrapperSelector: String,
        contentSelector: String,
        arrowSelector: String,
    };

    static targets = ['trigger', 'wrapper', 'content', 'arrow'];

    #showTimer = null;
    #hideTimer = null;
    #escape = (event) => {
        if (event.key === 'Escape') {
            this.#conceal();
        }
    };

    disconnect() {
        this.#conceal();
    }

    show() {
        clearTimeout(this.#hideTimer);
        clearTimeout(this.#showTimer);

        if (this.delayDurationValue > 0) {
            this.#showTimer = setTimeout(() => this.#reveal(), this.delayDurationValue);
        } else {
            this.#reveal();
        }
    }

    hide() {
        clearTimeout(this.#showTimer);
        clearTimeout(this.#hideTimer);
        this.#hideTimer = setTimeout(() => this.#conceal(), GRACE);
    }

    get #wrapper() {
        return this.hasWrapperTarget ? this.wrapperTarget : document.querySelector(this.wrapperSelectorValue);
    }

    get #content() {
        return this.hasContentTarget ? this.contentTarget : document.querySelector(this.contentSelectorValue);
    }

    get #arrow() {
        return this.hasArrowTarget ? this.arrowTarget : document.querySelector(this.arrowSelectorValue);
    }

    #reveal() {
        const wrapper = this.#wrapper;

        if (!wrapper || wrapper.matches(':popover-open')) {
            return;
        }

        wrapper.showPopover();
        this.#position(wrapper);
        document.addEventListener('keydown', this.#escape);
    }

    #conceal() {
        clearTimeout(this.#showTimer);
        clearTimeout(this.#hideTimer);
        document.removeEventListener('keydown', this.#escape);

        const wrapper = this.#wrapper;

        if (wrapper?.matches(':popover-open')) {
            wrapper.hidePopover();
        }
    }

    #position(wrapper) {
        const trigger = this.triggerTarget.getBoundingClientRect();
        const { top, left, side } = placement(
            trigger,
            { width: wrapper.offsetWidth, height: wrapper.offsetHeight },
            { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
            { side: wrapper.dataset.side, align: 'center', gap: ARROW + Number(wrapper.dataset.sideOffset ?? 0) },
        );

        wrapper.style.inset = `${top}px auto auto ${left}px`;
        wrapper.dataset.placed = side;

        for (const element of [this.#content, this.#arrow]) {
            if (element) {
                element.dataset.side = side;
            }
        }

        this.#mark(side, trigger, top, left, wrapper);
    }

    /** Writes on the bubble the side it went to, logical, and keeps the arrow on the trigger when the bubble was pushed. */
    #mark(side, trigger, top, left, wrapper) {
        const content = this.#content;
        const arrow = this.#arrow;

        if (!content) {
            return;
        }

        const rtl = getComputedStyle(wrapper).direction === 'rtl';

        for (const [name, on] of Object.entries(sideClasses(side, rtl))) {
            content.classList.toggle(name, on);
        }

        if (arrow) {
            const pushedX = (trigger.left + trigger.right - wrapper.offsetWidth) / 2 - left;
            const pushedY = (trigger.top + trigger.bottom - wrapper.offsetHeight) / 2 - top;
            const vertical = side === 'top' || side === 'bottom';

            arrow.style.setProperty('--phil-tooltip-arrow-x', `${vertical ? Math.round(pushedX) : 0}px`);
            arrow.style.setProperty('--phil-tooltip-arrow-y', `${vertical ? 0 : Math.round(pushedY)}px`);
        }
    }
}
