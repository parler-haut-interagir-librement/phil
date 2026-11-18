/*
 * The Stimulus controller of phil/slides for the speaker, as `@PhilSlides/presenter.html.twig` renders it: each slide
 * paired with its notes,
 *
 *     <article data-phil--slides--presenter-target="pair">
 *         <section id="slide-1" class="phil-slide" data-phil--slides--presenter-target="slide">…</section>
 *         <section class="phil-slides-presenter__notes">…</section>
 *     </article>
 *
 * then an aside, hidden without script, with the next slide, the timer and the controls. Connected, it marks the view
 * `isPresenting` and shows the `isCurrent` pair; the fragments still to come stay visible to the speaker, `isPending`.
 * A copy of the next slide, without its identifiers, stands in the aside, or a sentence at the end of the deck. The
 * timer counts the time elapsed once started, and the time left when the value `duration`, in seconds, is set: past
 * it, the time left is negative and marked `isOvertime`. The keys move the deck wherever the focus is; every window of
 * the same deck follows through the BroadcastChannel.
 */

import { clock, isoDuration } from './lib/deck.js';
import SlidesController from './lib/slides_controller.js';

export default class extends SlidesController {
    static targets = ['pair', 'side', 'next', 'end', 'elapsed', 'remaining', 'toggle'];

    static values = { duration: Number };

    connect() {
        this.element.classList.add('isPresenting');
        this.elapsed = 0;
        this.since = null;
        this.ticker = null;

        if (this.hasSideTarget) {
            this.sideTarget.hidden = false;
        }

        super.connect();
        this.showTime();
    }

    disconnect() {
        super.disconnect();
        this.stop();
        this.element.classList.remove('isPresenting');
    }

    render(announce) {
        super.render(announce);
        const { slide } = this.position;

        this.pairTargets.forEach((pair, index) => {
            pair.classList.toggle('isCurrent', index === slide);
        });

        if (!this.hasNextTarget) {
            return;
        }

        const following = this.slideTargets[slide + 1];
        this.nextTarget.replaceChildren(...(following ? [this.copy(following)] : []));

        if (this.hasEndTarget) {
            this.endTarget.hidden = Boolean(following);
        }
    }

    /** A slide to look at only: no identifier, no target, nothing to focus, every fragment shown. */
    copy(slide) {
        const copy = slide.cloneNode(true);

        for (const element of [copy, ...copy.querySelectorAll('*')]) {
            element.removeAttribute('id');
            element.removeAttribute('tabindex');
            element.removeAttribute(`data-${this.identifier}-target`);
            element.classList.remove('isPending');
        }

        copy.classList.add('isCurrent');

        return copy;
    }

    toggleTimer() {
        if (this.since === null) {
            this.since = Date.now();
            this.ticker = setInterval(() => this.showTime(), 1000);
        } else {
            this.stop();
        }

        this.showTime();
    }

    resetTimer() {
        this.elapsed = 0;
        this.since = this.since === null ? null : Date.now();
        this.showTime();
    }

    stop() {
        if (this.since !== null) {
            this.elapsed += (Date.now() - this.since) / 1000;
        }

        this.since = null;
        clearInterval(this.ticker);
        this.ticker = null;
    }

    seconds() {
        return this.elapsed + (this.since === null ? 0 : (Date.now() - this.since) / 1000);
    }

    showTime() {
        const seconds = this.seconds();
        const running = this.since !== null;

        if (this.hasElapsedTarget) {
            this.elapsedTarget.textContent = clock(seconds);
            this.elapsedTarget.setAttribute('datetime', isoDuration(seconds));
        }

        if (this.hasRemainingTarget && this.durationValue > 0) {
            const left = this.durationValue - seconds;
            this.remainingTarget.textContent = clock(left);
            this.remainingTarget.setAttribute('datetime', isoDuration(left));
            this.remainingTarget.classList.toggle('isOvertime', left < 0);
        }

        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-pressed', String(running));
            this.toggleTarget.textContent = running
                ? this.toggleTarget.dataset.pauseLabel
                : this.toggleTarget.dataset.startLabel;
        }
    }

    openAudience(event) {
        if (globalThis.open?.(event.currentTarget.href, 'phil-slides-audience', 'popup')) {
            event.preventDefault();
        }
    }
}
