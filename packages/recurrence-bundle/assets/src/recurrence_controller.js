/*
 * The Stimulus controller of phil/recurrence-bundle that shows the rows of a recurrence form its frequency and its
 * end use, as the bundle's RecurrenceRuleType renders it: each row carries `data-show`, the frequencies or ends it is
 * for — `WEEKLY DAILY`, `count`. A row shows when the frequency chosen is one of them, or the end chosen is; a hidden
 * row's fields are disabled, so that they are not sent. It runs when the page connects and on each change.
 */
import { Controller } from '@hotwired/stimulus';

export function shown(show, frequency, end) {
    const values = show.split(/\s+/);

    return frequency !== '' && (values.includes(frequency) || values.includes(end));
}

export default class extends Controller {
    static targets = ['frequency', 'end', 'row'];

    connect() {
        this.show();
    }

    show() {
        const frequency = this.frequencyTarget.value;
        const end = this.endTarget.value;

        for (const row of this.rowTargets) {
            const visible = shown(row.dataset.show, frequency, end);
            row.hidden = !visible;

            for (const control of row.querySelectorAll('input, select')) {
                control.disabled = !visible;
            }
        }
    }
}
