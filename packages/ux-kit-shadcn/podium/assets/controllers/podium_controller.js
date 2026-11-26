import { Controller } from '@hotwired/stimulus';

/** How long a place takes to rise on its step, and how long after the one before it starts, in milliseconds. */
export const RISE = 600;
export const STAGGER = 300;

/** The places rise from the third to the first: the winner last. */
export const ORDER = [3, 2, 1];

/** The DOM event that plays the `Celebration` of the page. */
export const CELEBRATE = 'celebrate';

/** How long the places of a podium take to stand on their steps, the last one included, in milliseconds. */
export function lasting(count) {
    const places = Math.max(1, Math.min(ORDER.length, Number(count) || 0));

    return RISE + STAGGER * (places - 1);
}

/*
 * A podium: its places wait below their steps, `data-state="waiting"`, then rise, `data-state="rising"`, the third first,
 * as the stylesheet draws it, when the podium comes into view with `autoplay`, or by the action `rise`; once they stand,
 * `data-state="risen"`, its message is said in its polite live region, the event `podium:risen` dispatched, and with an
 * `effect`, the `Celebration` of the page plays it. Where motion is reduced, by the system or by `data-motion="reduce"`
 * on an ancestor, the places stand from the start, and rising only says the message.
 *
 * @value message What is said once they stand on their steps.
 * @value effect The effect the `Celebration` of the page plays then; none when empty.
 * @value autoplay Whether they rise as soon as the podium comes into view.
 * @target message The live region that says the message.
 */
export default class extends Controller {
    static targets = ['message'];

    static values = {
        message: String,
        effect: String,
        autoplay: { type: Boolean, default: true },
    };

    connect() {
        this.timer = null;
        this.observer = null;
        this.risen = false;

        if (this.reduced()) {
            this.element.setAttribute('data-state', 'risen');
        } else {
            this.element.setAttribute('data-state', 'waiting');
        }

        if (!this.autoplayValue) {
            return;
        }

        if ('function' !== typeof globalThis.IntersectionObserver) {
            this.rise();

            return;
        }

        this.observer = new globalThis.IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.rise();
            }
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        clearTimeout(this.timer);
        this.timer = null;
        this.observer?.disconnect();
        this.observer = null;
    }

    /** The action: the places rise on their steps, once, then the message is said. */
    rise() {
        this.observer?.disconnect();
        this.observer = null;

        if (this.risen) {
            return;
        }

        this.risen = true;

        if (this.reduced()) {
            this.element.setAttribute('data-state', 'risen');
            this.stood();

            return;
        }

        // The waiting state is drawn before the rise starts, so that the steps move rather than appear.
        void this.element.offsetHeight;
        this.element.setAttribute('data-state', 'rising');
        this.timer = setTimeout(() => {
            this.timer = null;
            this.element.setAttribute('data-state', 'risen');
            this.stood();
        }, lasting(this.element.querySelectorAll('[data-place]').length));
    }

    /** Whether motion is reduced, by the system or by `data-motion="reduce"` on the element or an ancestor. */
    reduced() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches || null !== this.element.closest('[data-motion="reduce"]');
    }

    stood() {
        if (this.hasMessageTarget && '' !== this.messageValue) {
            this.messageTarget.textContent = this.messageValue;
        }

        this.dispatch('risen');

        if ('' !== this.effectValue) {
            // The message is said here: the celebration draws, and says only words of its own.
            window.dispatchEvent(new CustomEvent(CELEBRATE, { detail: { effect: this.effectValue } }));
        }
    }
}
