import { Controller } from '@hotwired/stimulus';

/**
 * The tab a key moves to from `index` among `count` tabs: the next and previous ones wrapping around, the side arrows
 * mirrored when the page reads right to left, the up and down arrows only for a vertical list, Home and End the first
 * and the last; `null` for any other key.
 */
export function moved(key, index, count, { rtl = false, vertical = false } = {}) {
    const next = (index + 1) % count;
    const previous = (index - 1 + count) % count;

    switch (key) {
        case 'ArrowRight':
            return vertical ? null : rtl ? previous : next;
        case 'ArrowLeft':
            return vertical ? null : rtl ? next : previous;
        case 'ArrowDown':
            return vertical ? next : null;
        case 'ArrowUp':
            return vertical ? previous : null;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

/*
 * Tabs as the WAI-ARIA pattern has them: one panel shown, the selected tab alone in the tab order, the arrows, Home and
 * End moving the selection. Without JavaScript, every panel shows.
 */
export default class extends Controller {
    static targets = ['trigger', 'tab'];

    static values = { activeTab: String };

    connect() {
        if (!this.activeTabValue && this.triggerTargets.length > 0) {
            this.activeTabValue = this.triggerTargets[0].dataset.tabId;
        }

        this.#render();
    }

    open(event) {
        this.activeTabValue = event.currentTarget.dataset.tabId;
    }

    move(event) {
        const trigger = event.currentTarget;
        const index = moved(event.key, this.triggerTargets.indexOf(trigger), this.triggerTargets.length, {
            rtl: getComputedStyle(trigger).direction === 'rtl',
            vertical: trigger.parentElement?.getAttribute('aria-orientation') === 'vertical',
        });

        if (index !== null) {
            event.preventDefault();
            this.activeTabValue = this.triggerTargets[index].dataset.tabId;
            this.triggerTargets[index].focus();
        }
    }

    activeTabValueChanged() {
        this.#render();
    }

    #render() {
        for (const trigger of this.triggerTargets) {
            const active = trigger.dataset.tabId === this.activeTabValue;
            trigger.setAttribute('aria-selected', String(active));
            trigger.setAttribute('tabindex', active ? '0' : '-1');
            trigger.dataset.active = String(active);
        }

        for (const tab of this.tabTargets) {
            const active = tab.dataset.tabId === this.activeTabValue;
            tab.hidden = !active;
            tab.classList.toggle('isCurrent', active);
            tab.dataset.active = String(active);
            tab.dataset.state = active ? 'active' : 'inactive';
        }
    }
}
