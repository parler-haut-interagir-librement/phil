import { Controller } from '@hotwired/stimulus';

/**
 * Says that the results of a closed poll are revealed: on connect, it dispatches the DOM event `poll:revealed` on the
 * window, its detail naming the poll and the labels of its winners, once per poll in a browser tab, so that coming back
 * to the page does not play it again. The kit's Celebration listens with
 * `data-action="poll:revealed@window->celebration#play"`; anything else may listen too.
 */
export default class extends Controller {
    static values = { poll: String, revealed: Boolean, winners: Array };

    connect() {
        if (!this.revealedValue || this.alreadySaid()) {
            return;
        }

        this.remember();
        this.dispatch('revealed', {
            prefix: 'poll',
            target: window,
            detail: { poll: this.pollValue, winners: this.winnersValue },
        });
    }

    key() {
        return `phil-poll-revealed:${this.pollValue}`;
    }

    // Storage may be refused, by a private window or the site's settings: the event is then dispatched every time.
    alreadySaid() {
        try {
            return window.sessionStorage.getItem(this.key()) === '1';
        } catch {
            return false;
        }
    }

    remember() {
        try {
            window.sessionStorage.setItem(this.key(), '1');
        } catch {
            // Nothing kept: the next visit says it again.
        }
    }
}
