import { Controller } from '@hotwired/stimulus';

import { matches } from './lib/search.js';

const STEPS = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };

/**
 * The picker made comfortable: a search by label, shown only when this controller runs, the arrow keys, Home and End
 * among the visible reactions, Escape and a click elsewhere to close. Without it the picker is a `<details>` of plain
 * buttons that work as they are.
 */
export default class extends Controller {
    static targets = ['summary', 'search', 'status', 'empty', 'group', 'option'];

    static values = { results: String };

    toggled() {
        if (!this.element.open) {
            return;
        }

        this.searchTarget.hidden = false;
        this.searchTarget.value = '';
        this.filter();
        this.searchTarget.focus();
    }

    filter() {
        const query = this.searchTarget.value;
        this.optionTargets.forEach((option) => {
            option.hidden = !matches(option.dataset.search, query);
        });
        this.groupTargets.forEach((group) => {
            group.hidden = this.optionTargets.every((option) => option.hidden || !group.contains(option));
        });
        const visible = this.visible();
        this.emptyTarget.hidden = visible.length > 0;
        this.statusTarget.textContent = this.resultsValue.replace('{count}', String(visible.length));
        this.rove(visible[0]);
    }

    keydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
        } else if (event.target === this.searchTarget) {
            this.keydownInSearch(event);
        } else if (this.optionTargets.includes(event.target)) {
            this.keydownOnOption(event);
        }
    }

    choose(event) {
        if (event.target.closest('button')) {
            this.close();
        }
    }

    outside(event) {
        if (this.element.open && !this.element.contains(event.target)) {
            this.element.open = false;
        }
    }

    keydownInSearch(event) {
        const first = this.visible()[0];

        if (event.key === 'Enter') {
            // The search has no name, and pressing Enter in it must not send the form with its first button.
            event.preventDefault();
            first?.click();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.moveTo(first);
        }
    }

    keydownOnOption(event) {
        const visible = this.visible();
        const index = visible.indexOf(event.target);
        const step = STEPS[event.key];
        let target = null;

        if (step !== undefined) {
            target = visible[index + step] ?? (index + step < 0 ? this.searchTarget : event.target);
        } else if (event.key === 'Home') {
            [target] = visible;
        } else if (event.key === 'End') {
            target = visible[visible.length - 1];
        }

        if (target) {
            event.preventDefault();
            this.moveTo(target);
        }
    }

    close() {
        this.element.open = false;
        this.summaryTarget.focus();
    }

    moveTo(target) {
        if (target && target !== this.searchTarget) {
            this.rove(target);
        }

        target?.focus();
    }

    rove(current) {
        this.optionTargets.forEach((option) => {
            option.tabIndex = option === current ? 0 : -1;
        });
    }

    visible() {
        return this.optionTargets.filter((option) => !option.hidden);
    }
}
