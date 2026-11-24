import { Controller } from '@hotwired/stimulus';

/*
 * The sticky header: it keeps `--phil-sticky-header-size` on the root at its height, which the scroll padding of the
 * page reads, so that anchors and the focus stop below it. With `hideOnScroll`, it hides while the reader scrolls
 * down and shows when they scroll up or near the top; hidden, it is `inert`, its links out of the reach of the keyboard
 * and of screen readers. It never hides where motion is reduced, nor while it holds the focus.
 */
export const SIZE = '--phil-sticky-header-size';

/** Whether the header is hidden after a scroll from `last` to `y`, given its height and the threshold. */
export function hiddenAfter({ y, last, height, threshold, hidden }) {
    if (y <= height) {
        return false;
    }

    if (y - last > threshold) {
        return true;
    }

    if (last - y > threshold) {
        return false;
    }

    return hidden;
}

export default class extends Controller {
    static values = {
        hideOnScroll: { type: Boolean, default: false },
        threshold: { type: Number, default: 8 },
    };

    #last = 0;

    #observer = null;

    #scroll = () => this.update(window.scrollY);

    connect() {
        this.motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.measure();

        if (typeof globalThis.ResizeObserver === 'function') {
            this.#observer = new ResizeObserver(() => this.measure());
            this.#observer.observe(this.element);
        }

        if (this.hideOnScrollValue) {
            this.#last = window.scrollY;
            window.addEventListener('scroll', this.#scroll, { passive: true });
        }
    }

    disconnect() {
        this.#observer?.disconnect();
        this.#observer = null;
        window.removeEventListener('scroll', this.#scroll);
        document.documentElement.style.removeProperty(SIZE);
        this.#hide(false);
    }

    /** Writes the height of the header on the root. */
    measure() {
        document.documentElement.style.setProperty(SIZE, `${this.element.offsetHeight}px`);
    }

    /** Hides or shows the header for a scroll to `y`. */
    update(y) {
        const held = this.reduced() || this.element.contains(document.activeElement);
        const hidden = held
            ? false
            : hiddenAfter({
                  y,
                  last: this.#last,
                  height: this.element.offsetHeight,
                  threshold: Math.max(0, this.thresholdValue),
                  hidden: this.element.inert === true,
              });

        this.#last = y;
        this.#hide(hidden);
    }

    /** Whether motion is reduced, by the system or by `data-motion="reduce"` on the element or an ancestor. */
    reduced() {
        return this.motion.matches || null !== this.element.closest('[data-motion="reduce"]');
    }

    #hide(hidden) {
        this.element.inert = hidden;

        if (hidden) {
            this.element.setAttribute('data-hidden', '');
        } else {
            this.element.removeAttribute('data-hidden');
        }
    }
}
