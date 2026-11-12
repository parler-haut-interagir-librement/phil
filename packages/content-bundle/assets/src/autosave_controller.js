/*
 * The Stimulus controller of phil/content-bundle's editing page, on its form:
 *
 *     <form data-controller="phil--content-bundle--autosave"
 *           data-phil--content-bundle--autosave-url-value="/admin/contents/news:42/fr/autosave"
 *           data-action="input->phil--content-bundle--autosave#schedule">
 *
 * Once the typing stops for `delay` milliseconds, it posts the form — its fields and its CSRF token, never its buttons
 * — to the autosave endpoint, which keeps it for the signed-in author alone. One save runs at a time; a change made
 * meanwhile is saved after it. The form's `data-autosave-state` says `saving`, `saved` or `failed`, for a style or a
 * live region to show. Nothing is kept in the browser.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        delay: { type: Number, default: 2000 },
    };

    #timer = null;
    #saving = false;
    #pending = false;

    disconnect() {
        clearTimeout(this.#timer);
    }

    schedule() {
        clearTimeout(this.#timer);
        this.#timer = setTimeout(() => this.save(), this.delayValue);
    }

    async save() {
        if (this.#saving) {
            this.#pending = true;

            return;
        }

        this.#saving = true;
        this.element.dataset.autosaveState = 'saving';

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: new FormData(this.element),
                credentials: 'same-origin',
                headers: { Accept: 'text/plain' },
            });
            this.element.dataset.autosaveState = response.ok ? 'saved' : 'failed';
        } catch {
            this.element.dataset.autosaveState = 'failed';
        } finally {
            this.#saving = false;
        }

        if (this.#pending) {
            this.#pending = false;
            await this.save();
        }
    }
}
