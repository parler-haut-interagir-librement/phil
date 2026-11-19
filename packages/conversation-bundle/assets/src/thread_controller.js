import { Controller } from '@hotwired/stimulus';

/**
 * Loads the messages of an open conversation again when the Mercure hub says a new one came in it: the update names
 * the conversation and the message, never the body, and the messages are read from the bundle's page, which checks
 * that the reader takes part in the conversation. An update about another conversation is left alone; a failed load
 * leaves the messages as they were, until the next update or a reload.
 */
export default class extends Controller {
    static targets = ['messages'];

    static values = { hub: String, conversation: String, url: String };

    connect() {
        if (!this.hubValue || typeof EventSource === 'undefined') {
            return;
        }

        this.source = new EventSource(this.hubValue, { withCredentials: true });
        this.source.onmessage = (event) => this.received(event);
    }

    disconnect() {
        this.source?.close();
        this.source = null;
    }

    async received(event) {
        let update;

        try {
            update = JSON.parse(event.data);
        } catch {
            return;
        }

        if (update?.conversation !== this.conversationValue || !this.hasMessagesTarget) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'text/html' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            this.messagesTarget.innerHTML = await response.text();
            this.dispatch('updated', { detail: { message: update.message } });
        } catch {
            // The messages stay as they were; the next update or a reload shows the new one.
        }
    }
}
