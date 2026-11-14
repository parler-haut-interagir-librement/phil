import { Controller } from '@hotwired/stimulus';

/*
 * The two buttons of a number field: each steps it as its arrows would, tells the page, and stops at its limit.
 */
export default class extends Controller {
    static targets = ['input', 'decrement', 'increment'];

    connect() {
        this.decrementTarget.hidden = false;
        this.incrementTarget.hidden = false;
        this.refresh();
    }

    decrement() {
        this.#step('stepDown');
    }

    increment() {
        this.#step('stepUp');
    }

    refresh() {
        const input = this.inputTarget;
        const value = input.valueAsNumber;
        const locked = input.disabled || input.readOnly;
        const reached = (limit, beyond) => limit !== '' && Number.isFinite(value) && beyond(value, Number(limit));

        this.decrementTarget.disabled = locked || reached(input.min, (a, b) => a <= b);
        this.incrementTarget.disabled = locked || reached(input.max, (a, b) => a >= b);
    }

    #step(method) {
        const input = this.inputTarget;
        input[method]();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        this.refresh();
    }
}
