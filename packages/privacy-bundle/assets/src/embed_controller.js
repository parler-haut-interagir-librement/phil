/*
 * The Stimulus controller of a third-party frame blocked by phil/privacy-bundle until its consent category is granted:
 *
 *     <div data-controller="phil--privacy-bundle--embed"
 *          data-phil--privacy-bundle--embed-category-value="videos"
 *          data-action="phil--privacy-bundle--consent:changed@window->phil--privacy-bundle--embed#changed">
 *         …
 *         <button type="button" hidden data-phil--privacy-bundle--embed-target="once"
 *                 data-action="phil--privacy-bundle--embed#once">Load this one only</button>
 *         <template data-phil--privacy-bundle--embed-target="frame"><iframe …></iframe></template>
 *     </div>
 *
 * The frame replaces the preview when the category is granted anywhere on the page, or, for this frame only and
 * without saving a choice, when the person presses the button that this controller shows.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'once'];
    static values = { category: String };

    connect() {
        if (this.hasOnceTarget) {
            this.onceTarget.hidden = false;
        }
    }

    changed(event) {
        if (event.detail.granted.includes(this.categoryValue)) {
            this.load();
        }
    }

    once() {
        this.load();
    }

    load() {
        if (!this.hasFrameTarget) {
            return;
        }

        this.element.replaceWith(document.importNode(this.frameTarget.content, true));
    }
}
