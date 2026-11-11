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

const isDisabled = (item) => item.dataset.disabled === 'true';

/*
 * A menu on the popover API, opened by its invoker and closed by the browser on Escape and on a click outside, the focus
 * going back to the button. The controller places it, moves the focus between its items and runs their roles.
 */
export default class extends Controller {
    static targets = ['trigger', 'content', 'item'];

    static values = { open: { type: Boolean, default: false } };

    #connected = false;
    #focusOnOpen = 'first';
    #typed = '';
    #typedTimer = null;
    #follow = () => this.#place();

    connect() {
        this.#apply();
        this.#connected = true;
    }

    disconnect() {
        this.#connected = false;
        this.#stopFollowing();
        clearTimeout(this.#typedTimer);
    }

    itemTargetConnected(item) {
        item.tabIndex = -1;
    }

    toggle(event) {
        if (event?.currentTarget?.hasAttribute('popovertarget')) {
            return;
        }

        this.openValue ? this.close() : this.open();
    }

    open({ focus = 'first' } = {}) {
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
            window.addEventListener('scroll', this.#follow, { passive: true, capture: true });
            window.addEventListener('resize', this.#follow, { passive: true });
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
            const items = this.#itemsOf(this.contentTarget);
            items[this.#focusOnOpen === 'last' ? items.length - 1 : 0]?.focus();
        }

        this.#focusOnOpen = 'first';
    }

    closeFromItem(event) {
        const item = event.currentTarget;

        if (isDisabled(item)) {
            event.preventDefault();

            return;
        }

        this.#closeAfter(item);
    }

    toggleCheckbox(event) {
        const item = event.currentTarget;

        if (isDisabled(item)) {
            event.preventDefault();

            return;
        }

        const checked = item.getAttribute('aria-checked') !== 'true';
        item.setAttribute('aria-checked', String(checked));
        this.#indicate(item, '[data-checkbox-indicator]', checked);
        this.#closeAfter(item);
    }

    selectRadio(event) {
        const item = event.currentTarget;

        if (isDisabled(item)) {
            event.preventDefault();

            return;
        }

        const group = item.closest('[data-slot="dropdown-menu-radio-group"]');

        if (!group) {
            return;
        }

        group.dataset.value = item.dataset.value;

        for (const radio of group.querySelectorAll('[role="menuitemradio"]')) {
            radio.setAttribute('aria-checked', String(radio === item));
            this.#indicate(radio, '[data-radio-indicator]', radio === item);
        }

        this.#closeAfter(item);
    }

    onTriggerKeydown(event) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            this.open({ focus: event.key === 'ArrowDown' ? 'first' : 'last' });
        }
    }

    onContentKeydown(event) {
        const item = event.target.closest('[role^="menuitem"]');
        const menu = item ? item.closest('[role="menu"]') : this.contentTarget;
        const items = this.#itemsOf(menu);
        const index = items.indexOf(item);
        const count = items.length;
        const rtl = getComputedStyle(menu).direction === 'rtl';

        switch (event.key) {
            case 'ArrowDown':
                this.#move(event, items[(index + 1) % count]);
                break;
            case 'ArrowUp':
                this.#move(event, items[index <= 0 ? count - 1 : index - 1]);
                break;
            case 'Home':
                this.#move(event, items[0]);
                break;
            case 'End':
                this.#move(event, items[count - 1]);
                break;
            case 'ArrowRight':
            case 'ArrowLeft':
                if ((event.key === 'ArrowRight') !== rtl) {
                    this.#openSub(event, item);
                } else {
                    this.#closeSub(event, menu);
                }

                break;
            case 'Escape':
                // The browser gives the focus back only when the outermost popover closes.
                this.#closeSub(event, menu);
                break;
            case 'Enter':
            case ' ':
                if (item && item.tagName !== 'BUTTON') {
                    event.preventDefault();
                    item.click();
                }

                break;
            case 'Tab':
                this.close({ focus: false });
                break;
            default:
                this.#type(event, items);
        }
    }

    #itemsOf(menu) {
        return this.itemTargets.filter((item) => item.closest('[role="menu"]') === menu && !isDisabled(item));
    }

    #indicate(item, selector, shown) {
        const indicator = item.querySelector(selector);

        if (indicator) {
            indicator.hidden = !shown;
        }
    }

    #closeAfter(item) {
        if (item.dataset.closeOnSelect === 'true') {
            this.close();
        }
    }

    #move(event, item) {
        event.preventDefault();
        item?.focus();
    }

    #openSub(event, item) {
        if (item?.getAttribute('aria-haspopup') !== 'menu') {
            return;
        }

        event.preventDefault();
        const sub = item.parentElement.querySelector('[role="menu"]');

        if (!sub.matches(':popover-open')) {
            sub.showPopover();
        }

        this.#itemsOf(sub)[0]?.focus();
    }

    #closeSub(event, menu) {
        if (menu === this.contentTarget) {
            return;
        }

        event.preventDefault();
        menu.hidePopover();
        menu.parentElement.querySelector('[aria-haspopup="menu"]')?.focus();
    }

    #type(event, items) {
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        clearTimeout(this.#typedTimer);
        this.#typed += event.key.toLocaleLowerCase();
        this.#typedTimer = setTimeout(() => {
            this.#typed = '';
        }, TYPEAHEAD);
        items.find((item) => item.textContent.trim().toLocaleLowerCase().startsWith(this.#typed))?.focus();
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
