import { Controller } from '@hotwired/stimulus';

/**
 * Sends a vote of helpfulness without leaving the page: the form works without JavaScript, and this controller only
 * posts it in the background, asking for JSON, then writes the new counts and which button is pressed. When the request
 * fails, the form is sent as usual, and the page that comes back says why.
 */
export default class extends Controller {
    static targets = ['counts', 'helpful', 'unhelpful'];

    static values = { label: String };

    async vote(event) {
        if (this.sending) {
            return;
        }

        event.preventDefault();
        const button = event.currentTarget;
        const body = new FormData(this.element);
        body.set(button.name, button.value);
        this.sending = true;

        try {
            const response = await fetch(this.element.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            this.show(await response.json());
            this.sending = false;
        } catch {
            this.element.requestSubmit(button);
        }
    }

    show({ helpful, unhelpful, vote }) {
        if (this.hasCountsTarget && this.labelValue) {
            this.countsTarget.textContent = this.labelValue
                .replace('{helpful}', String(helpful))
                .replace('{unhelpful}', String(unhelpful));
        }

        this.press(this.hasHelpfulTarget ? this.helpfulTarget : null, vote === true, 'helpful');
        this.press(this.hasUnhelpfulTarget ? this.unhelpfulTarget : null, vote === false, 'unhelpful');
    }

    press(button, pressed, value) {
        if (!button) {
            return;
        }

        button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        button.value = pressed ? 'retract' : value;
    }
}
