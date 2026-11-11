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

/*
 * A sub-menu on a nested popover: the pointer opens and closes it after the delays asked for; the keyboard goes through
 * the controller of the menu that holds it.
 */
export default class extends Controller {
    static targets = ['trigger', 'content'];

    static values = {
        openDelay: { type: Number, default: 0 },
        closeDelay: { type: Number, default: 0 },
    };

    #timer = null;

    disconnect() {
        clearTimeout(this.#timer);
    }

    show() {
        this.#after(this.openDelayValue, () => {
            if (!this.contentTarget.matches(':popover-open')) {
                this.contentTarget.showPopover();
            }
        });
    }

    hide() {
        this.#after(this.closeDelayValue, () => {
            if (this.contentTarget.matches(':popover-open')) {
                this.contentTarget.hidePopover();
            }
        });
    }

    sync(event) {
        const open = event.newState === 'open';
        const state = open ? 'open' : 'closed';

        this.contentTarget.dataset.state = state;
        this.triggerTarget.dataset.state = state;
        this.triggerTarget.setAttribute('aria-expanded', String(open));

        if (open) {
            this.#place();
        }
    }

    #after(delay, action) {
        clearTimeout(this.#timer);

        if (delay > 0) {
            this.#timer = setTimeout(action, delay);
        } else {
            action();
        }
    }

    #place() {
        const content = this.contentTarget;
        const direction = getComputedStyle(content).direction === 'rtl' ? 'left' : 'right';
        const { top, left, side } = placement(
            this.triggerTarget.getBoundingClientRect(),
            { width: content.offsetWidth, height: content.offsetHeight },
            { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
            { side: direction, align: 'start', gap: 2 },
        );

        content.style.inset = `${top}px auto auto ${left}px`;
        content.dataset.placed = side;
    }
}
