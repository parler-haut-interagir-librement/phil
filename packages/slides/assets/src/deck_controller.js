/*
 * The Stimulus controller of phil/slides for the audience, as `@PhilSlides/deck.html.twig` renders it:
 *
 *     <section class="phil-slides" data-controller="phil--slides--deck" data-phil--slides--deck-channel-value="…">
 *         <p role="status" aria-live="polite" data-phil--slides--deck-target="status"></p>
 *         <div class="phil-slides__stage" tabindex="0">
 *             <section id="slide-1" class="phil-slide" data-phil--slides--deck-target="slide">…</section>
 *         </div>
 *         <nav hidden data-phil--slides--deck-target="controls">…</nav>
 *     </section>
 *
 * Without it, the slides follow one another. Connected, it marks the deck `isPresenting` and shows one slide at a
 * time, the `isCurrent` one, its fragments revealed one by one — those still to come are `isPending`. It starts at the
 * slide the address names, `#slide-3`, and keeps the address on the current slide. The arrows, Page Up and Page Down,
 * the space bar, Home and End move it while the focus is in the deck, or anywhere in the page when the value `pageKeys`
 * is true, or in full screen; F toggles full screen, Escape leaves it. The live region says each new slide. The link to
 * the presenter view opens it in a window of its own; both follow each other through their BroadcastChannel.
 */

import { slideFromHash } from './lib/deck.js';
import SlidesController from './lib/slides_controller.js';

export default class extends SlidesController {
    static targets = ['stage', 'controls', 'fullscreen'];

    static values = { pageKeys: Boolean };

    connect() {
        this.element.classList.add('isPresenting');

        if (this.hasControlsTarget) {
            this.controlsTarget.hidden = false;
        }

        super.connect();
    }

    disconnect() {
        super.disconnect();
        this.element.classList.remove('isPresenting', 'isFullscreen');

        if (this.hasControlsTarget) {
            this.controlsTarget.hidden = true;
        }

        for (const slide of this.slideTargets) {
            slide.classList.remove('isCurrent');
        }

        for (const fragment of this.fragments.flat()) {
            fragment.classList.remove('isPending');
        }
    }

    start() {
        this.position.go(slideFromHash(globalThis.location?.hash, this.position.count) ?? 0, 0);
    }

    owns() {
        return this.pageKeysValue || this.element.contains(globalThis.document?.activeElement) || this.isFullscreen();
    }

    run(name) {
        if (name === 'fullscreen') {
            this.toggleFullscreen();

            return true;
        }

        if (name === 'exit') {
            if (!this.isFullscreen()) {
                return false;
            }

            globalThis.document.exitFullscreen?.();

            return true;
        }

        return super.run(name);
    }

    render(announce) {
        super.render(announce);
        const anchor = this.slideTargets[this.position.slide]?.id;

        if (anchor && globalThis.location?.hash !== `#${anchor}`) {
            globalThis.history?.replaceState?.(globalThis.history.state, '', `#${anchor}`);
        }
    }

    hashChanged() {
        const index = slideFromHash(globalThis.location?.hash, this.position.count);

        if (index !== null && index !== this.position.slide) {
            this.move(this.position.go(index, 0));
        }
    }

    toggleFullscreen() {
        if (this.isFullscreen()) {
            globalThis.document.exitFullscreen?.();
        } else {
            this.element.requestFullscreen?.();
        }
    }

    fullscreenChanged() {
        const full = this.isFullscreen();
        this.element.classList.toggle('isFullscreen', full);

        if (this.hasFullscreenTarget) {
            this.fullscreenTarget.setAttribute('aria-pressed', String(full));
        }
    }

    isFullscreen() {
        return globalThis.document?.fullscreenElement === this.element;
    }

    openPresenter(event) {
        if (globalThis.open?.(event.currentTarget.href, 'phil-slides-presenter', 'popup')) {
            event.preventDefault();
        }
    }
}
