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

/** How long the letters typed in a row add up, in milliseconds. */
export const TYPEAHEAD = 500;

/** How far a scroll button moves the options, in pixels. */
const SCROLL_STEP = 96;

/** The first of `labels` starting with `typed`, ignoring case; -1 when none does. */
export function typeahead(labels, typed) {
    const start = typed.toLocaleLowerCase();

    return labels.findIndex((label) => label.toLocaleLowerCase().startsWith(start));
}

/*
 * A select-only combobox over a listbox on the popover API, the invoker opening it and the browser closing it on Escape
 * and on a click outside. The focus goes on the options; the chosen value is written in the hidden select of the form.
 */
export default class extends Controller {
    static targets = [
        'trigger',
        'value',
        'content',
        'viewport',
        'item',
        'hiddenSelect',
        'scrollUpButton',
        'scrollDownButton',
    ];

    static values = {
        open: { type: Boolean, default: false },
        value: { type: String, default: '' },
    };

    #connected = false;
    #focusOnOpen = false;
    #typed = '';
    #typedTimer = null;
    #follow = () => this.#place();

    connect() {
        this.#render();
        this.#apply();
        this.#connected = true;
    }

    disconnect() {
        this.#connected = false;
        this.#stopFollowing();
        clearTimeout(this.#typedTimer);
    }

    toggle(event) {
        if (event?.currentTarget?.hasAttribute('popovertarget')) {
            return;
        }

        this.openValue ? this.close() : this.open();
    }

    open({ focus = true } = {}) {
        this.#focusOnOpen = focus;
        this.openValue = true;
    }

    close({ focus = true } = {}) {
        this.openValue = false;

        if (focus && this.hasTriggerTarget) {
            this.triggerTarget.focus();
        }
    }

    sync(event) {
        const open = event.newState === 'open';

        if (open) {
            this.#place();
            this.onViewportScroll();
            window.addEventListener('scroll', this.#follow, { passive: true, capture: true });
            window.addEventListener('resize', this.#follow, { passive: true });
            this.#focusOnOpen ||= this.openValue === false;
        } else {
            this.#stopFollowing();
        }

        this.openValue = open;
    }

    openValueChanged() {
        if (!this.#connected) {
            return;
        }

        this.#apply();

        if (this.openValue && this.#focusOnOpen) {
            this.#focusOnOpen = false;
            const options = this.#enabled;
            (
                options.find((item) => item.dataset.value === this.valueValue) ??
                options[0] ??
                this.contentTarget
            ).focus();
        }
    }

    valueValueChanged() {
        if (this.#connected) {
            this.#render();
        }
    }

    selectItem(event) {
        const item = event.currentTarget;

        if (item.dataset.disabled !== 'true') {
            this.#choose(item);
        }
    }

    highlightItem(event) {
        if (event.currentTarget.dataset.disabled !== 'true') {
            event.currentTarget.focus({ preventScroll: true });
        }
    }

    unhighlightItem() {
        this.contentTarget.focus({ preventScroll: true });
    }

    onTriggerKeydown(event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.open();
        }
    }

    onContentKeydown(event) {
        const options = this.#enabled;
        const index = options.indexOf(event.target);
        const count = options.length;

        switch (event.key) {
            case 'ArrowDown':
                this.#move(event, options[(index + 1) % count]);
                break;
            case 'ArrowUp':
                this.#move(event, options[index <= 0 ? count - 1 : index - 1]);
                break;
            case 'Home':
                this.#move(event, options[0]);
                break;
            case 'End':
                this.#move(event, options[count - 1]);
                break;
            case 'Enter':
            case ' ':
                event.preventDefault();

                if (index >= 0) {
                    this.#choose(options[index]);
                }

                break;
            case 'Tab':
                this.close({ focus: false });
                break;
            default:
                this.#type(event, options);
        }
    }

    scrollUp() {
        this.viewportTarget.scrollBy({ top: -SCROLL_STEP });
    }

    scrollDown() {
        this.viewportTarget.scrollBy({ top: SCROLL_STEP });
    }

    onViewportScroll() {
        const viewport = this.viewportTarget;

        this.scrollUpButtonTargets.forEach((button) => {
            button.hidden = viewport.scrollTop <= 0;
        });
        this.scrollDownButtonTargets.forEach((button) => {
            button.hidden = viewport.scrollTop + viewport.clientHeight >= viewport.scrollHeight;
        });
    }

    get #enabled() {
        return this.itemTargets.filter((item) => item.dataset.disabled !== 'true');
    }

    #label(item) {
        return item.querySelector('[data-slot="select-item-text"]')?.textContent.trim() ?? '';
    }

    #move(event, item) {
        event.preventDefault();
        item?.focus();
    }

    #type(event, options) {
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        clearTimeout(this.#typedTimer);
        this.#typed += event.key;
        this.#typedTimer = setTimeout(() => {
            this.#typed = '';
        }, TYPEAHEAD);
        options[
            typeahead(
                options.map((item) => this.#label(item)),
                this.#typed,
            )
        ]?.focus();
    }

    #choose(item) {
        this.valueValue = item.dataset.value;
        this.dispatch('change', { detail: { value: this.valueValue, label: this.#label(item) }, bubbles: true });
        this.close();
    }

    #render() {
        const selected = this.itemTargets.find(
            (item) => item.dataset.value === this.valueValue && this.valueValue !== '',
        );

        for (const item of this.itemTargets) {
            item.setAttribute('aria-selected', String(item === selected));
            const indicator = item.querySelector('[data-slot="select-item-indicator"]');

            if (indicator) {
                indicator.hidden = item !== selected;
            }
        }

        for (const value of this.valueTargets) {
            value.textContent = selected ? this.#label(selected) : value.dataset.placeholder;
        }

        if (this.hasTriggerTarget) {
            this.triggerTarget.dataset.hasValue = String(this.valueValue !== '');
        }

        if (this.hasHiddenSelectTarget) {
            const option = this.hiddenSelectTarget.options[0];
            option.value = this.valueValue;
            option.textContent = selected ? this.#label(selected) : '';
            this.hiddenSelectTarget.value = this.valueValue;

            if (this.#connected) {
                this.hiddenSelectTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }

    #apply() {
        const open = this.openValue;
        const state = open ? 'open' : 'closed';

        this.element.dataset.state = state;

        if (this.hasTriggerTarget) {
            this.triggerTarget.dataset.state = state;
            this.triggerTarget.setAttribute('aria-expanded', String(open));
        }

        if (this.hasContentTarget) {
            this.contentTarget.dataset.state = state;

            if (open !== this.contentTarget.matches(':popover-open')) {
                open ? this.contentTarget.showPopover() : this.contentTarget.hidePopover();
            }
        }
    }

    #place() {
        if (!this.hasTriggerTarget) {
            return;
        }

        const content = this.contentTarget;
        const { top, left, side } = placement(
            this.triggerTarget.getBoundingClientRect(),
            { width: content.offsetWidth, height: content.offsetHeight },
            { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight },
            {
                side: content.dataset.side,
                align: content.dataset.align,
                rtl: getComputedStyle(content).direction === 'rtl',
            },
        );

        content.style.inset = `${top}px auto auto ${left}px`;
        content.dataset.placed = side;
    }

    #stopFollowing() {
        window.removeEventListener('scroll', this.#follow, { capture: true });
        window.removeEventListener('resize', this.#follow);
    }
}
