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

const viewport = () => ({ width: document.documentElement.clientWidth, height: document.documentElement.clientHeight });
const rtl = (element) => getComputedStyle(element).direction === 'rtl';

/*
 * A popover on the HTML popover API: the browser opens it from its invoker, closes it on Escape and on a click outside.
 * The controller keeps the state of the panel in its value and its attributes, places it next to the trigger, moves
 * the focus inside, and closes the other popovers of its group.
 */
export default class extends Controller {
    static targets = ['trigger', 'content'];

    static values = {
        open: { type: Boolean, default: false },
        name: { type: String, default: '' },
    };

    #connected = false;
    #follow = () => this.#place();

    connect() {
        this.updateState();
        this.#connected = true;
    }

    disconnect() {
        this.#connected = false;
        this.#stopFollowing();
    }

    toggle(event) {
        // An invoker opens and closes the panel itself; opening again what a click outside just closed is avoided.
        if (event?.currentTarget?.hasAttribute('popovertarget')) {
            return;
        }

        this.openValue = !this.openValue;
    }

    close() {
        this.openValue = false;
    }

    sync(event) {
        if (event.newState === 'open') {
            this.#place();
            window.addEventListener('scroll', this.#follow, { passive: true, capture: true });
            window.addEventListener('resize', this.#follow, { passive: true });
        } else {
            this.#stopFollowing();
        }

        this.openValue = event.newState === 'open';
    }

    openValueChanged() {
        if (!this.#connected) {
            return;
        }

        this.updateState();

        if (this.openValue) {
            if (this.nameValue) {
                window.dispatchEvent(
                    new CustomEvent('popover:open', { detail: { name: this.nameValue, source: this.element } }),
                );
            }

            this.#focusContent();
        }
    }

    handleGroupOpen(event) {
        if (
            this.openValue &&
            this.nameValue &&
            event.detail.name === this.nameValue &&
            event.detail.source !== this.element
        ) {
            this.openValue = false;
        }
    }

    handleOutsideClick(event) {
        if (this.openValue && !this.element.contains(event.target)) {
            this.openValue = false;
        }
    }

    handleEscape() {
        if (this.openValue) {
            this.openValue = false;
            this.triggerTargets[0]?.focus();
        }
    }

    updateState() {
        const open = this.openValue;
        const state = open ? 'open' : 'closed';
        const content = this.contentTargets[0];

        this.element.dataset.state = state;

        for (const trigger of this.triggerTargets) {
            trigger.dataset.state = state;
            trigger.setAttribute('aria-expanded', String(open));
        }

        if (content) {
            content.dataset.state = state;

            if (open !== content.matches(':popover-open')) {
                open ? content.showPopover() : content.hidePopover();
            }
        }
    }

    #place() {
        const trigger = this.triggerTargets[0];
        const content = this.contentTargets[0];

        if (!trigger || !content) {
            return;
        }

        const { top, left, side } = placement(
            trigger.getBoundingClientRect(),
            { width: content.offsetWidth, height: content.offsetHeight },
            viewport(),
            { side: content.dataset.side, align: content.dataset.align, rtl: rtl(content) },
        );

        content.style.inset = `${top}px auto auto ${left}px`;
        content.dataset.placed = side;
    }

    #focusContent() {
        this.contentTargets[0]
            ?.querySelector(
                '[autofocus], input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])',
            )
            ?.focus();
    }

    #stopFollowing() {
        window.removeEventListener('scroll', this.#follow, { capture: true });
        window.removeEventListener('resize', this.#follow);
    }
}
