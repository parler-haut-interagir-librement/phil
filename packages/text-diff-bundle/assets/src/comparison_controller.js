import { Controller } from '@hotwired/stimulus';

import { label, normalizeMode, step, stops } from './lib/marks.js';

/**
 * The comparison of two texts or two documents written by the text_diff filters: switches between the inline view,
 * the old text alone and the new text alone, and steps from change to change, announcing where it stands.
 *
 * The view is written on the element as `data-text-diff-mode`, which the stylesheet reads; each `mode` target, a
 * button with a `mode` param, is pressed when its view is shown. `next` and `previous` move to the following or
 * preceding change the view shows — a replacement is one change in the inline view —, mark it `text-diff-current`,
 * focus it and bring it into view; the `counter` target, a live region, says which change it is.
 */
export default class extends Controller {
    static targets = ['output', 'counter', 'mode'];

    static values = {
        mode: { type: String, default: 'inline' },
        counterText: { type: String, default: '{current} of {total}' },
        totalText: { type: String, default: '{total} change(s)' },
        noneText: { type: String, default: 'No changes' },
    };

    connect() {
        this.current = -1;
        this.modeValueChanged();
    }

    show({ params: { mode } }) {
        this.modeValue = normalizeMode(mode);
    }

    next() {
        this.move(1);
    }

    previous() {
        this.move(-1);
    }

    modeValueChanged() {
        const mode = normalizeMode(this.modeValue);
        this.element.dataset.textDiffMode = mode;

        for (const button of this.modeTargets) {
            button.setAttribute(
                'aria-pressed',
                String(mode === button.getAttribute(`data-${this.identifier}-mode-param`)),
            );
        }

        this.clear();
        this.current = -1;
        this.announce(this.changes().length);
    }

    move(delta) {
        const changes = this.changes();
        this.clear();
        this.current = step(this.current, changes.length, delta);
        this.announce(changes.length);

        if (this.current < 0) {
            return;
        }

        const marks = changes[this.current];
        marks.forEach((mark) => {
            mark.classList.add('text-diff-current');
        });
        marks[0].setAttribute('aria-current', 'true');
        marks[0].setAttribute('tabindex', '-1');
        marks[0].focus({ preventScroll: true });
        marks[0].scrollIntoView({ block: 'center', behavior: this.reducedMotion() ? 'auto' : 'smooth' });
    }

    changes() {
        const root = this.hasOutputTarget ? this.outputTarget : this.element;
        const elements = [...root.querySelectorAll('ins, del')];
        const marks = elements.map((element, index) => ({
            tag: element.localName,
            joinsPrevious: index > 0 && adjacent(elements[index - 1], element),
        }));

        return stops(marks, normalizeMode(this.modeValue)).map((group) => group.map((index) => elements[index]));
    }

    clear() {
        for (const mark of this.element.querySelectorAll('.text-diff-current')) {
            mark.classList.remove('text-diff-current');
            mark.removeAttribute('aria-current');
        }
    }

    announce(total) {
        if (!this.hasCounterTarget) {
            return;
        }

        if (0 === total) {
            this.counterTarget.textContent = this.noneTextValue;
        } else if (this.current < 0) {
            this.counterTarget.textContent = label(this.totalTextValue, 0, total);
        } else {
            this.counterTarget.textContent = label(this.counterTextValue, this.current + 1, total);
        }
    }

    reducedMotion() {
        return 'function' === typeof window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
}

/** Whether `mark` comes right after `previous`, with nothing but white space between them. */
function adjacent(previous, mark) {
    let node = mark.previousSibling;

    while (node && 3 === node.nodeType && '' === node.textContent.trim()) {
        node = node.previousSibling;
    }

    return node === previous;
}
