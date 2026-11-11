import { Controller } from '@hotwired/stimulus';

/**
 * The trigger a key moves to from `index` among `count`: along the orientation, the side arrows mirrored when the page
 * reads right to left, Home and End the first and the last; `null` for any other key.
 */
export function moved(key, index, count, { orientation = 'vertical', rtl = false } = {}) {
    const next = (index + 1) % count;
    const previous = (index - 1 + count) % count;
    const horizontal = orientation === 'horizontal';

    switch (key) {
        case 'ArrowDown':
            return horizontal ? null : next;
        case 'ArrowUp':
            return horizontal ? null : previous;
        case 'ArrowRight':
            return horizontal ? (rtl ? previous : next) : null;
        case 'ArrowLeft':
            return horizontal ? (rtl ? next : previous) : null;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

/*
 * The arrows, Home and End between the summaries of an accordion. Opening and closing, and keeping one item open at a
 * time through the `name` of its `details`, are the browser's work.
 */
export default class extends Controller {
    static targets = ['item', 'trigger', 'content'];

    static values = {
        multiple: { type: Boolean, default: false },
        orientation: { type: String, default: 'vertical' },
    };

    toggle(event) {
        const trigger = event.currentTarget;

        if (trigger.tagName === 'SUMMARY') {
            return;
        }

        const item = trigger.closest('details');

        if (item && !item.inert) {
            item.open = !item.open;
        }
    }

    handleKeydown(event) {
        const triggers = this.triggerTargets.filter((trigger) => !trigger.closest('details')?.inert);
        const index = moved(event.key, triggers.indexOf(event.currentTarget), triggers.length, {
            orientation: this.orientationValue,
            rtl: getComputedStyle(this.element).direction === 'rtl',
        });

        if (index !== null) {
            event.preventDefault();
            triggers[index].focus();
        }
    }
}
