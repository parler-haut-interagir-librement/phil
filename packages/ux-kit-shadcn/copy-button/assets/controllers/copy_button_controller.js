import { Controller } from '@hotwired/stimulus';

/*
 * Copies a text to the clipboard, says so on the button for a moment and in the live region. The button shows only
 * where the browser can copy.
 */
export default class extends Controller {
    static targets = ['trigger', 'status'];

    static values = {
        text: String,
        source: String,
        label: String,
        copiedLabel: String,
        failedLabel: String,
        duration: { type: Number, default: 2000 },
    };

    #timer = null;

    connect() {
        this.triggerTarget.hidden = !navigator.clipboard;
    }

    disconnect() {
        clearTimeout(this.#timer);
    }

    async copy() {
        try {
            await navigator.clipboard.writeText(this.#text());
            this.#say(this.copiedLabelValue);
            this.dispatch('copied');
        } catch {
            this.#say(this.failedLabelValue);
        }
    }

    #text() {
        if (this.sourceValue === '') {
            return this.textValue;
        }

        const source = document.querySelector(this.sourceValue);

        return source?.value ?? source?.textContent ?? '';
    }

    #say(message) {
        clearTimeout(this.#timer);
        this.triggerTarget.textContent = message;
        this.statusTarget.textContent = message;
        this.#timer = setTimeout(() => {
            this.triggerTarget.textContent = this.labelValue;
            this.statusTarget.textContent = '';
        }, this.durationValue);
    }
}
