/*
 * The Stimulus controller of phil/content-warnings-bundle that reveals content folded behind its warnings, as the
 * bundle's template `@PhilContentWarnings/fold.html.twig` renders it:
 *
 *     <div data-controller="phil--content-warnings-bundle--reveal">
 *         <p role="note"><strong>Content warning:</strong> Gore (severe)</p>
 *         <details data-phil--content-warnings-bundle--reveal-target="details"
 *                  data-action="toggle->phil--content-warnings-bundle--reveal#toggle">
 *             <summary>Show the content</summary>
 *             <div tabindex="-1" data-phil--content-warnings-bundle--reveal-target="content">…</div>
 *         </details>
 *     </div>
 *
 * The details element does the folding, with the keyboard and without script. The controller dispatches `revealed`
 * and `concealed` when the reader opens or closes it, and gives other controls `reveal` and `conceal`: `reveal` opens
 * the content, then moves the focus to it and scrolls it into view — at once when the reader asks for reduced motion,
 * smoothly otherwise.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['details', 'content'];

    toggle() {
        this.dispatch(this.detailsTarget.open ? 'revealed' : 'concealed');
    }

    reveal(event) {
        event?.preventDefault();
        this.detailsTarget.open = true;

        if (this.hasContentTarget) {
            this.contentTarget.focus({ preventScroll: true });
            this.contentTarget.scrollIntoView({ block: 'nearest', behavior: this.reducedMotion() ? 'auto' : 'smooth' });
        }
    }

    conceal(event) {
        event?.preventDefault();
        this.detailsTarget.open = false;
    }

    reducedMotion() {
        return globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    }
}
